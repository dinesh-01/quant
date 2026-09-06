<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Moves a test case into another suite in the same project.
 *
 * The case keeps its `external_id`, and therefore its `PREFIX-N` identifier, so
 * links and bug reports quoting it stay valid. Moving to another project is
 * refused because that number is only unique within its own project; copy the
 * case instead, which allocates a fresh number from the target.
 */
final class MoveTestCase
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException when the target suite is in another project
     */
    public function __invoke(User $user, TestCase $case, TestSuite $targetSuite): TestCase
    {
        $case->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $case->testProject);

        if ($targetSuite->test_project_id !== $case->test_project_id) {
            throw ValidationException::withMessages([
                'test_suite_id' => 'A test case cannot be moved to a different test project. Copy it instead.',
            ]);
        }

        $from = $case->test_suite_id;

        $case->test_suite_id = $targetSuite->getKey();
        $case->sort_order = (int) TestCase::query()
            ->where('test_suite_id', $targetSuite->getKey())
            ->whereKeyNot($case->getKey())
            ->max('sort_order') + 1;
        $case->save();

        $this->audit->record(AuditAction::TestCaseMoved, $user, $case, [
            'name' => $case->name,
            'from_test_suite_id' => $from,
            'to_test_suite_id' => $targetSuite->getKey(),
        ]);

        return $case;
    }
}
