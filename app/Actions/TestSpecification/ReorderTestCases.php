<?php

namespace App\Actions\TestSpecification;

use App\Enums\Ability;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Renumbers the test cases in a suite into the given order.
 */
final class ReorderTestCases
{
    /**
     * @param  array<int, int>  $orderedIds  every case id in the suite, in the order wanted
     *
     * @throws AuthorizationException
     * @throws ValidationException when the ids are not exactly the suite's cases
     */
    public function __invoke(User $user, TestSuite $suite, array $orderedIds): void
    {
        $suite->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $suite->testProject);

        $caseIds = TestCase::query()
            ->where('test_suite_id', $suite->getKey())
            ->pluck('id')
            ->all();

        sort($caseIds);
        $given = $orderedIds;
        sort($given);

        if ($given !== $caseIds) {
            throw ValidationException::withMessages([
                'order' => 'The order must list every test case in the suite exactly once.',
            ]);
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                TestCase::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
