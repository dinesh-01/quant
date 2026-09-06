<?php

namespace Tests\Feature\Reports;

use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ProjectReportsTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $project = TestProject::factory()->create();

        $this->get(route('reports.project', $project))->assertRedirect(route('login'));
    }

    public function test_the_page_is_forbidden_without_a_metrics_ability(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('reports.project', $project))
            ->assertForbidden();
    }

    public function test_view_project_metrics_shows_each_plans_latest_build_status(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Release 42']);
        $build = Build::factory()->for($plan, 'testPlan')->create(['name' => 'Test Run']);
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCan($project, Ability::ViewProjectMetrics, Ability::ViewPlanMetrics);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Passed]);

        $this->actingAs($user)
            ->get(route('reports.project', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/project')
                ->where('can.dashboard', true)
                ->has('plans', 1)
                ->where('plans.0.name', 'Release 42')
                ->where('plans.0.items', 1)
                ->where('plans.0.build.name', 'Test Run')
                ->where('plans.0.counts.passed', 1)
                ->where('currentProject.can.viewReports', true)
            );
    }

    public function test_view_plan_metrics_alone_lists_plans_without_dashboard_counts(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Release 42']);
        TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCan($project, Ability::ViewPlanMetrics);

        $this->actingAs($user)
            ->get(route('reports.project', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/project')
                ->where('can.dashboard', false)
                ->has('plans', 1)
                ->where('plans.0.name', 'Release 42')
                ->where('plans.0.counts', null)
            );
    }
}
