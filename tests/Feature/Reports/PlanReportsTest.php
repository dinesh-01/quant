<?php

namespace Tests\Feature\Reports;

use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\Milestone;
use App\Models\Requirement;
use App\Models\RequirementCoverage;
use App\Models\RequirementSpec;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class PlanReportsTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $plan = TestPlan::factory()->create();

        $this->get(route('reports.plan', $plan))->assertRedirect(route('login'));
    }

    public function test_reading_plan_reports_requires_view_plan_metrics(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewExecutions);

        $this->actingAs($user)
            ->get(route('reports.plan', $plan))
            ->assertForbidden();
    }

    public function test_a_draft_run_does_not_count_and_the_latest_completed_run_wins(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $passed = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $untouched = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($passed, 'testPlanItem')
            ->create(['status' => ExecutionStatus::Failed, 'is_draft' => true]);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($passed, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Failed]);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($passed, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Passed]);

        $this->actingAs($user)
            ->get(route('reports.plan', ['testPlan' => $plan, 'build' => $build->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/plan')
                ->where('status.total', 2)
                ->where('status.counts.passed', 1)
                ->where('status.counts.failed', 0)
                ->where('status.counts.blocked', 0)
                ->where('status.counts.not_run', 1)
                ->where('currentProject.can.viewReports', true)
            );
    }

    public function test_tester_progress_scores_assigned_items_from_the_latest_run(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $tester = User::factory()->create(['name' => 'Ada']);
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        TesterAssignment::factory()
            ->for($item, 'testPlanItem')
            ->create([
                'build_id' => $build->id,
                'user_id' => $tester->id,
            ]);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Blocked]);

        $this->actingAs($user)
            ->get(route('reports.plan', ['testPlan' => $plan, 'build' => $build->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('testers', 1)
                ->where('testers.0.name', 'Ada')
                ->where('testers.0.assigned', 1)
                ->where('testers.0.blocked', 1)
                ->where('testers.0.passed', 0)
                ->where('testers.0.not_run', 0)
            );
    }

    public function test_milestone_progress_compares_passed_items_to_urgency_targets(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $high = TestPlanItem::factory()->for($plan, 'testPlan')->urgent()->create();
        TestPlanItem::factory()->for($plan, 'testPlan')->urgent()->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        Milestone::factory()->for($plan, 'testPlan')->create([
            'name' => 'Beta',
            'high_percent' => 100,
            'medium_percent' => 80,
            'low_percent' => 50,
        ]);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($high, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Passed]);

        $this->actingAs($user)
            ->get(route('reports.plan', ['testPlan' => $plan, 'build' => $build->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('milestones', 1)
                ->where('milestones.0.name', 'Beta')
                ->where('milestones.0.bands.high.items', 2)
                ->where('milestones.0.bands.high.passed', 1)
                ->where('milestones.0.bands.high.actual_percent', 50)
                ->where('milestones.0.bands.high.target_percent', 100)
            );
    }

    public function test_requirement_coverage_lists_covered_and_uncovered_requirements(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $spec = RequirementSpec::factory()->for($project)->create();
        $covered = Requirement::factory()->for($project)->for($spec)->withVersion()->create([
            'doc_id' => 'REQ-1',
            'name' => 'Login works',
        ]);
        Requirement::factory()->for($project)->for($spec)->create([
            'doc_id' => 'REQ-2',
            'name' => 'Logout works',
        ]);
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        RequirementCoverage::factory()->create([
            'requirement_version_id' => $covered->latestVersion->id,
            'test_case_version_id' => $item->test_case_version_id,
        ]);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Passed]);

        $this->actingAs($user)
            ->get(route('reports.plan', ['testPlan' => $plan, 'build' => $build->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('coverage.covered', 1)
                ->where('coverage.covered.0.doc_id', 'REQ-1')
                ->where('coverage.covered.0.status', 'passed')
                ->where('coverage.covered.0.passed_items', 1)
                ->has('coverage.uncovered', 1)
                ->where('coverage.uncovered.0.doc_id', 'REQ-2')
            );
    }

    public function test_status_csv_lists_each_item_and_its_latest_status(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Release 42']);
        $build = Build::factory()->for($plan, 'testPlan')->named('Test Run')->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Failed]);

        $response = $this->actingAs($user)
            ->get(route('reports.plan-status', ['testPlan' => $plan, 'build' => $build->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('external_id,name,platform,status', $response->streamedContent());
        $this->assertStringContainsString('failed', $response->streamedContent());
    }

    public function test_status_over_time_counts_completed_runs_by_day(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $first = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $second = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($first, 'testPlanItem')
            ->completed()
            ->create([
                'status' => ExecutionStatus::Passed,
                'executed_at' => '2026-09-01 10:00:00',
            ]);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($first, 'testPlanItem')
            ->completed()
            ->create([
                'status' => ExecutionStatus::Failed,
                'executed_at' => '2026-09-01 16:00:00',
            ]);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($second, 'testPlanItem')
            ->completed()
            ->create([
                'status' => ExecutionStatus::Blocked,
                'executed_at' => '2026-09-02 09:00:00',
            ]);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($second, 'testPlanItem')
            ->create([
                'status' => ExecutionStatus::Passed,
                'is_draft' => true,
                'executed_at' => '2026-09-02 11:00:00',
            ]);

        $this->actingAs($user)
            ->get(route('reports.plan', ['testPlan' => $plan, 'build' => $build->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('timeline', 2)
                ->where('timeline.0.date', '2026-09-01')
                ->where('timeline.0.passed', 1)
                ->where('timeline.0.failed', 1)
                ->where('timeline.0.blocked', 0)
                ->where('timeline.0.total', 2)
                ->where('timeline.1.date', '2026-09-02')
                ->where('timeline.1.blocked', 1)
                ->where('timeline.1.total', 1)
            );
    }

    public function test_status_xlsx_lists_each_item_and_its_latest_status(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Release 42']);
        $build = Build::factory()->for($plan, 'testPlan')->named('Test Run')->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->completed()
            ->create(['status' => ExecutionStatus::Failed]);

        $response = $this->actingAs($user)
            ->get(route('reports.plan-status-xlsx', ['testPlan' => $plan, 'build' => $build->id]));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('external_id', $sheet->getCell('A1')->getValue());
        $this->assertSame('failed', $sheet->getCell('D2')->getValue());

        unlink($path);
    }

    public function test_a_build_from_another_plan_is_not_found(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $other = TestPlan::factory()->for($project)->create();
        $foreignBuild = Build::factory()->for($other, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewPlanMetrics);

        $this->actingAs($user)
            ->get(route('reports.plan', ['testPlan' => $plan, 'build' => $foreignBuild->id]))
            ->assertNotFound();
    }
}
