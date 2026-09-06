<?php

namespace App\Actions\TestSpecification;

use App\Enums\Ability;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Renumbers a version's steps into the given order.
 *
 * Steps are renumbered contiguously from 1 in a transaction. `sort_order` has
 * no unique constraint precisely so this can happen in a single pass; MySQL
 * cannot defer a unique check to commit, so a unique column would collide
 * halfway through.
 *
 * Legacy's equivalent, `lib/ajax/stepReorder.php`, carried a comment saying "No
 * authorization checks" and interpolated the ids straight into SQL. This action
 * authorizes and binds.
 */
final class ReorderTestCaseSteps
{
    /**
     * @param  array<int, int>  $orderedIds  every step id in the version, in the order wanted
     *
     * @throws AuthorizationException
     * @throws ValidationException when the version is frozen or the ids are not exactly its steps
     */
    public function __invoke(User $user, TestCaseVersion $version, array $orderedIds): void
    {
        $version->loadMissing('testCase.testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $version->testCase->testProject);

        if ($version->isFrozen()) {
            throw ValidationException::withMessages([
                'order' => 'This test case version is frozen and cannot be changed.',
            ]);
        }

        $stepIds = TestCaseStep::query()
            ->where('test_case_version_id', $version->getKey())
            ->pluck('id')
            ->all();

        sort($stepIds);
        $given = $orderedIds;
        sort($given);

        if ($given !== $stepIds) {
            throw ValidationException::withMessages([
                'order' => 'The order must list every step in this version exactly once.',
            ]);
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                TestCaseStep::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
