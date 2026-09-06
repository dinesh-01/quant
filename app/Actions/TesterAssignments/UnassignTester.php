<?php

namespace App\Actions\TesterAssignments;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TesterAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a tester from a planned case on one build.
 */
final class UnassignTester
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TesterAssignment $assignment): void
    {
        $assignment->loadMissing(['testPlanItem.testPlan', 'testPlanItem.testCaseVersion.testCase', 'build', 'user']);

        $plan = $assignment->testPlanItem->testPlan;

        Gate::forUser($user)->authorize(Ability::AssignTesters->value, $plan);

        $this->audit->record(AuditAction::TesterUnassigned, $user, $plan, [
            'name' => $plan->name,
            'build' => $assignment->build->name,
            'tester_id' => $assignment->user_id,
            'tester' => $assignment->user->name,
            'test_case_id' => $assignment->testPlanItem->testCaseVersion->test_case_id,
            'test_case_name' => $assignment->testPlanItem->testCaseVersion->testCase->name,
        ]);

        $assignment->delete();
    }
}
