<?php

namespace Tests\Feature\Executions;

use App\Actions\Executions\RecordExecution;
use App\Actions\TesterAssignments\AssignTester;
use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\ExecutionStep;
use App\Models\Role;
use App\Models\TestCaseStep;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ExecutionLifecycleTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_tester_can_save_a_draft_and_complete_it(): void
    {
        [$plan, $item, $build, $user] = $this->runnable();

        $draft = app(RecordExecution::class)($user, $item, $build, [
            'status' => ExecutionStatus::Failed,
            'notes' => 'Sandbox down',
            'steps' => [],
        ], false);

        $this->assertTrue($draft->is_draft);
        $this->assertSame(ExecutionStatus::Failed, $draft->status);
        $this->assertSame($item->testCaseVersion->version, $draft->version);

        $done = app(RecordExecution::class)($user, $item, $build, [
            'status' => ExecutionStatus::Passed,
            'notes' => 'Retry worked',
            'steps' => [],
        ], true);

        $this->assertTrue($done->is($draft));
        $this->assertFalse($done->is_draft);
        $this->assertSame(ExecutionStatus::Passed, $done->status);
        $this->assertSame(1, Execution::query()->count());
    }

    public function test_a_draft_pins_each_case_step_on_the_run(): void
    {
        [, $item, $build, $user] = $this->runnable();
        $caseStep = TestCaseStep::factory()->for($item->testCaseVersion, 'testCaseVersion')->create([
            'sort_order' => 1,
        ]);

        $draft = app(RecordExecution::class)($user, $item, $build, [
            'status' => ExecutionStatus::Failed,
            'steps' => [
                [
                    'test_case_step_id' => $caseStep->id,
                    'status' => ExecutionStatus::Blocked,
                    'notes' => 'Timeout',
                ],
            ],
        ], false);

        $row = ExecutionStep::query()->where('execution_id', $draft->id)->sole();

        $this->assertSame($caseStep->id, $row->test_case_step_id);
        $this->assertSame(1, $row->sort_order);
        $this->assertSame(ExecutionStatus::Blocked, $row->status);
        $this->assertSame('Timeout', $row->notes);
    }

    public function test_a_closed_plan_refuses_new_results(): void
    {
        [$plan, $item, $build, $user] = $this->runnable();
        $plan->is_open = false;
        $plan->save();

        try {
            app(RecordExecution::class)($user, $item, $build, [
                'status' => ExecutionStatus::Passed,
                'steps' => [],
            ], true);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('build_id', $exception->errors());
        }
    }

    public function test_assigned_only_testers_cannot_run_unassigned_items(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan(
            $plan,
            Ability::ExecuteTests,
            Ability::ExecuteOnlyAssignedTestCases,
        );

        try {
            app(RecordExecution::class)($user, $item, $build, [
                'status' => ExecutionStatus::Passed,
                'steps' => [],
            ], true);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('item', $exception->errors());
        }
    }

    public function test_an_assigned_tester_can_run_their_item(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters, Ability::ExecuteTests);
        $tester = $this->userWhoCanOnPlan(
            $plan,
            Ability::ExecuteTests,
            Ability::ExecuteOnlyAssignedTestCases,
        );

        app(AssignTester::class)($leader, $item, $build, $tester);

        $execution = app(RecordExecution::class)($tester, $item, $build, [
            'status' => ExecutionStatus::Passed,
            'steps' => [],
        ], true);

        $this->assertFalse($execution->is_draft);
        $this->assertSame($tester->id, $execution->tester_id);
    }

    public function test_a_super_admin_can_run_an_unassigned_item(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = User::factory()->for(Role::factory()->superAdmin())->create();

        $execution = app(RecordExecution::class)($user, $item, $build, [
            'status' => ExecutionStatus::Passed,
            'steps' => [],
        ], true);

        $this->assertFalse($execution->is_draft);
        $this->assertSame($user->id, $execution->tester_id);
    }

    public function test_completing_as_not_run_is_refused(): void
    {
        [, $item, $build, $user] = $this->runnable();

        try {
            app(RecordExecution::class)($user, $item, $build, [
                'status' => ExecutionStatus::NotRun,
                'steps' => [],
            ], true);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_recording_without_execute_is_forbidden(): void
    {
        [$plan, $item, $build] = $this->runnable();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewExecutions);

        $this->expectException(AuthorizationException::class);

        app(RecordExecution::class)($user, $item, $build, [
            'status' => ExecutionStatus::Passed,
            'steps' => [],
        ], true);
    }

    /**
     * @return array{0: TestPlan, 1: TestPlanItem, 2: Build, 3: User}
     */
    private function runnable(): array
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);

        return [$plan, $item, $build, $user];
    }
}
