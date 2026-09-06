<?php

namespace App\Actions\TestSpecification;

use App\Enums\Ability;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Renumbers a set of sibling suites into the given order.
 *
 * `sort_order` is renumbered contiguously from 1 rather than patched, because
 * it carries no unique constraint and legacy drifted into sparse orders that
 * made drag and drop unpredictable.
 */
final class ReorderTestSuites
{
    /**
     * @param  array<int, int>  $orderedIds  every sibling id, in the order wanted
     *
     * @throws AuthorizationException
     * @throws ValidationException when the ids are not exactly the parent's children
     */
    public function __invoke(User $user, TestProject $project, ?TestSuite $parent, array $orderedIds): void
    {
        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $project);

        $siblingIds = TestSuite::query()
            ->where('test_project_id', $project->getKey())
            ->where('parent_id', $parent?->getKey())
            ->pluck('id')
            ->all();

        sort($siblingIds);
        $given = $orderedIds;
        sort($given);

        if ($given !== $siblingIds) {
            throw ValidationException::withMessages([
                'order' => 'The order must list every sibling test suite exactly once.',
            ]);
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                TestSuite::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
