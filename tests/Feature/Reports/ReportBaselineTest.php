<?php

namespace Tests\Feature\Reports;

use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\ReportBaseline;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ReportBaselineTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_saving_a_baseline_requires_view_plan_metrics(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewExecutions);

        $this->actingAs($user)
            ->post(route('reports.plan-baselines.store', $plan), [
                'name' => 'Beta cut',
            ])
            ->assertForbidden();
    }

    public function test_a_baseline_snapshots_the_live_status_report(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Passed]);

        $this->actingAs($user)
            ->post(route('reports.plan-baselines.store', $plan), [
                'name' => 'Beta cut',
                'build' => $build->id,
            ])
            ->assertRedirect(route('reports.plan', ['testPlan' => $plan, 'build' => $build->id]));

        $baseline = ReportBaseline::query()->sole();

        $this->assertSame('Beta cut', $baseline->name);
        $this->assertSame($plan->id, $baseline->test_plan_id);
        $this->assertSame($build->id, $baseline->build_id);
        $this->assertSame($user->id, $baseline->user_id);
        $this->assertSame(1, $baseline->total);
        $this->assertSame(1, $baseline->counts['passed']);
        $this->assertSame('passed', $baseline->items[0]['status']);
    }

    public function test_a_duplicate_baseline_name_on_the_same_plan_is_rejected(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        ReportBaseline::factory()->for($plan, 'testPlan')->create([
            'build_id' => $build->id,
            'name' => 'Beta cut',
        ]);

        $this->actingAs($user)
            ->post(route('reports.plan-baselines.store', $plan), [
                'name' => 'Beta cut',
                'build' => $build->id,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_comparing_a_baseline_lists_items_whose_status_changed(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Passed]);

        $this->actingAs($user)
            ->post(route('reports.plan-baselines.store', $plan), [
                'name' => 'Beta cut',
                'build' => $build->id,
            ]);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Failed]);

        $baseline = ReportBaseline::query()->sole();

        $this->actingAs($user)
            ->get(route('reports.plan', [
                'testPlan' => $plan,
                'build' => $build->id,
                'baseline' => $baseline->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comparison.baseline.name', 'Beta cut')
                ->where('comparison.live_counts.failed', 1)
                ->where('comparison.baseline_counts.passed', 1)
                ->has('comparison.changed', 1)
                ->where('comparison.changed.0.live', 'failed')
                ->where('comparison.changed.0.baseline', 'passed')
            );
    }

    public function test_a_baseline_from_another_plan_is_not_found(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $other = TestPlan::factory()->for($project)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $foreign = ReportBaseline::factory()->for($other, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        $this->actingAs($user)
            ->get(route('reports.plan', [
                'testPlan' => $plan,
                'build' => $build->id,
                'baseline' => $foreign->id,
            ]))
            ->assertNotFound();
    }
}
