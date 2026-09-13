<?php

namespace Tests\Feature\Executions;

use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Jobs\ProcessReportedExecutions;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ProcessReportedExecutionsTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_records_each_report_for_an_active_user(): void
    {
        [$user, $first, $second, $build] = $this->runnablePair();

        $this->app->call([
            new ProcessReportedExecutions($user->id, [
                $this->report($first, $build, ExecutionStatus::Passed),
                $this->report($second, $build, ExecutionStatus::Blocked, 'Flaky'),
            ]),
            'handle',
        ]);

        $this->assertSame(2, Execution::query()->count());
        $this->assertSame(ExecutionStatus::Passed, Execution::query()->where('test_plan_item_id', $first->id)->value('status'));
        $this->assertSame('Flaky', Execution::query()->where('test_plan_item_id', $second->id)->value('notes'));
    }

    public function test_skips_a_missing_item_and_records_the_rest(): void
    {
        [$user, $item, , $build] = $this->runnablePair();

        $this->app->call([
            new ProcessReportedExecutions($user->id, [
                $this->report($item, $build, ExecutionStatus::Passed),
                [
                    'item_id' => $item->id + 9999,
                    'build_id' => $build->id,
                    'status' => ExecutionStatus::Failed->value,
                    'notes' => null,
                    'duration' => null,
                    'steps' => [],
                    'complete' => true,
                ],
            ]),
            'handle',
        ]);

        $this->assertSame(1, Execution::query()->count());
        $this->assertSame($item->id, Execution::query()->value('test_plan_item_id'));
    }

    public function test_does_not_record_when_the_user_is_inactive(): void
    {
        [$user, $item, , $build] = $this->runnablePair();
        $user->forceFill(['is_active' => false])->save();

        $this->app->call([
            new ProcessReportedExecutions($user->id, [
                $this->report($item, $build, ExecutionStatus::Passed),
            ]),
            'handle',
        ]);

        $this->assertSame(0, Execution::query()->count());
    }

    public function test_skips_an_unassigned_item_for_an_assigned_only_tester(): void
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

        $this->app->call([
            new ProcessReportedExecutions($user->id, [
                $this->report($item, $build, ExecutionStatus::Passed),
            ]),
            'handle',
        ]);

        $this->assertSame(0, Execution::query()->count());
    }

    /**
     * @return array{0: User, 1: TestPlanItem, 2: TestPlanItem, 3: Build}
     */
    private function runnablePair(): array
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $first = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $second = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);

        return [$user, $first, $second, $build];
    }

    /**
     * @return array{item_id: int, build_id: int, status: string, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: string, notes: string|null}>, complete: bool}
     */
    private function report(TestPlanItem $item, Build $build, ExecutionStatus $status, ?string $notes = null): array
    {
        return [
            'item_id' => $item->id,
            'build_id' => $build->id,
            'status' => $status->value,
            'notes' => $notes,
            'duration' => null,
            'steps' => [],
            'complete' => true,
        ];
    }
}
