<?php

namespace App\Actions\TesterAssignments;

use App\Actions\Audit\AuditLogger;
use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TesterAssignmentStatus;
use App\Models\Build;
use App\Models\TesterAssignment;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Asks a tester to run one planned case on one build.
 *
 * The assignee must be able to execute on this plan: assigning someone who
 * cannot open the run would only produce a row they can never honour.
 */
final class AssignTester
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RoleResolver $roles,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        TestPlanItem $item,
        Build $build,
        User $tester,
        TesterAssignmentStatus $status = TesterAssignmentStatus::Open,
        ?Carbon $deadline = null,
    ): TesterAssignment {
        $item->loadMissing('testPlan');
        $build->loadMissing('testPlan');

        Gate::forUser($user)->authorize(Ability::AssignTesters->value, $item->testPlan);

        if ($build->test_plan_id !== $item->test_plan_id) {
            throw ValidationException::withMessages([
                'build' => 'That build does not belong to this plan.',
            ]);
        }

        if (! $tester->isActive()) {
            throw ValidationException::withMessages([
                'tester' => 'That account cannot be assigned.',
            ]);
        }

        if (! $this->roles->allows($tester, Ability::ExecuteTests, $item->testPlan)) {
            throw ValidationException::withMessages([
                'tester' => 'That person cannot execute tests on this plan.',
            ]);
        }

        $alreadyAssigned = TesterAssignment::query()
            ->where('test_plan_item_id', $item->getKey())
            ->where('build_id', $build->getKey())
            ->where('user_id', $tester->getKey())
            ->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'tester' => 'That person is already assigned to this case on this build.',
            ]);
        }

        $assignment = new TesterAssignment;
        $assignment->testPlanItem()->associate($item);
        $assignment->build()->associate($build);
        $assignment->user()->associate($tester);
        $assignment->assigner()->associate($user);
        $assignment->status = $status;
        $assignment->deadline_at = $deadline;
        $assignment->save();

        $item->loadMissing('testCaseVersion.testCase');

        $this->audit->record(AuditAction::TesterAssigned, $user, $item->testPlan, [
            'name' => $item->testPlan->name,
            'build' => $build->name,
            'tester_id' => $tester->getKey(),
            'tester' => $tester->name,
            'test_case_id' => $item->testCaseVersion->test_case_id,
            'test_case_name' => $item->testCaseVersion->testCase->name,
            'status' => $status->value,
        ]);

        return $assignment;
    }
}
