<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Re-parents a suite within its own project, carrying its whole subtree.
 *
 * Suites never move between projects: their cases hold external ids allocated
 * from the project counter, so a cross-project move would break `PREFIX-N`
 * uniqueness. Copying is the cross-project operation.
 */
final class MoveTestSuite
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Move the suite under a new parent, or to the project root when null.
     *
     * @throws AuthorizationException
     * @throws ValidationException when the target is in another project, inside the suite's own subtree, or too deep
     */
    public function __invoke(User $user, TestSuite $suite, ?TestSuite $newParent): TestSuite
    {
        $suite->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $suite->testProject);

        if ($newParent !== null) {
            $this->assertTargetIsUsable($suite, $newParent);
        }

        $from = $suite->parent_id;

        $suite->parent_id = $newParent?->getKey();
        $suite->sort_order = $this->nextSortOrder($suite, $newParent);
        $suite->save();

        /**
         * Recorded by hand rather than from `changes()`: the useful fact is
         * where it went, and `sort_order` is churn a reader does not want.
         */
        $this->audit->record(AuditAction::TestSuiteMoved, $user, $suite, [
            'name' => $suite->name,
            'from_parent_id' => $from,
            'to_parent_id' => $suite->parent_id,
        ]);

        return $suite;
    }

    /**
     * @throws ValidationException
     */
    private function assertTargetIsUsable(TestSuite $suite, TestSuite $newParent): void
    {
        if ($newParent->test_project_id !== $suite->test_project_id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A test suite cannot be moved to a different test project.',
            ]);
        }

        if ($newParent->is($suite) || $suite->isAncestorOf($newParent)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A test suite cannot be moved into itself or one of its own child suites.',
            ]);
        }

        if ($newParent->depth() + 1 + $suite->descendantDepth() > TestSuite::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => 'That move would nest test suites more than '.TestSuite::MAX_DEPTH.' levels deep.',
            ]);
        }
    }

    /**
     * Place the suite after its new last sibling.
     */
    private function nextSortOrder(TestSuite $suite, ?TestSuite $newParent): int
    {
        return (int) TestSuite::query()
            ->where('test_project_id', $suite->test_project_id)
            ->where('parent_id', $newParent?->getKey())
            ->whereKeyNot($suite->getKey())
            ->max('sort_order') + 1;
    }
}
