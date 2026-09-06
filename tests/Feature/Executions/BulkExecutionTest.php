<?php

namespace Tests\Feature\Executions;

use App\Actions\Executions\BulkRecordExecutions;
use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class BulkExecutionTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_selected_items_can_be_completed_together(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $first = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $second = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);

        $recorded = app(BulkRecordExecutions::class)(
            $user,
            $plan,
            $build,
            [$first->id, $second->id],
            ExecutionStatus::Passed,
        );

        $this->assertCount(2, $recorded);
        $this->assertSame(2, Execution::query()->where('is_draft', false)->count());
        $this->assertTrue($recorded[0]->status === ExecutionStatus::Passed);
    }

    public function test_bulk_complete_refuses_not_run(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);

        try {
            app(BulkRecordExecutions::class)(
                $user,
                $plan,
                $build,
                [$item->id],
                ExecutionStatus::NotRun,
            );
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_selected_items_can_be_completed_through_http(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $first = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $second = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);

        $this->actingAs($user)
            ->post(route('executions.bulk', $plan), [
                'build_id' => $build->id,
                'item_ids' => [$first->id, $second->id],
                'status' => ExecutionStatus::Passed->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Execution::query()->where('is_draft', false)->count());
    }
}
