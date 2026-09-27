<?php

namespace Tests\Feature\TestProjects;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\ExecutionStatus;
use App\Enums\TestCaseImportance;
use App\Models\AuditEvent;
use App\Models\Build;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseScriptLink;
use App\Models\TestCaseVersion;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class ProjectOverviewTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $project = TestProject::factory()->create();

        $this->get(route('projects.show', $project))->assertRedirect(route('login'));
    }

    public function test_a_member_sees_the_overview_for_their_project(): void
    {
        $project = TestProject::factory()->create(['name' => 'Checkout']);
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('overview/show')
                ->where('project.name', 'Checkout')
                ->where('overview.cases', 0)
                ->where('overview.cases_this_week', 0)
                ->where('overview.active_runs', 0)
                ->where('overview.runs_in_progress', 0)
                ->where('overview.runs_blocked', 0)
                ->where('overview.previous_build', null)
                ->has('overview.runs', 0)
                ->has('overview.needs_attention', 0)
                ->has('overview.activity', 0)
                ->has('availableProjects', 1)
                ->where('availableProjects.0.id', $project->id)
                ->where('currentProject.counts', ['suites' => 0, 'plans' => 0, 'issues' => 0]),
            );
    }

    public function test_the_overview_reports_run_progress_attention_and_deltas(): void
    {
        $this->travelTo('2026-09-11 14:00:00');

        $project = TestProject::factory()->create(['name' => 'Checkout', 'prefix' => 'CO']);
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $user->update(['name' => 'Maya Singh']);
        $tester = User::factory()->create(['name' => 'Sam Shah']);

        $suite = TestSuite::factory()->for($project)->create();
        TestCaseModel::factory()->for($suite, 'testSuite')->create([
            'created_at' => now()->subWeeks(2),
        ]);
        $fresh = TestCaseModel::factory()->for($suite, 'testSuite')->create();
        $freshVersion = TestCaseVersion::factory()->for($fresh, 'testCase')->create();
        TestCaseScriptLink::factory()->for($freshVersion, 'testCaseVersion')->create();

        $sanity = TestPlan::factory()->for($project)->create(['name' => 'Mobile Checkout · Sanity']);
        $rcBuild = Build::factory()->for($sanity, 'testPlan')->create(['name' => 'Build 4.8-rc']);
        $blockedItem = TestPlanItem::factory()->for($sanity, 'testPlan')->create();
        TestPlanItem::factory()->for($sanity, 'testPlan')->create();
        Execution::factory()
            ->for($sanity, 'testPlan')
            ->for($rcBuild, 'build')
            ->for($blockedItem, 'testPlanItem')
            ->completed()
            ->create([
                'status' => ExecutionStatus::Blocked,
                'notes' => 'sandbox down',
            ]);

        $regression = TestPlan::factory()->for($project)->create(['name' => 'Checkout · Regression']);
        $previousBuild = Build::factory()->for($regression, 'testPlan')->create(['name' => 'Build 4.7']);
        $latestBuild = Build::factory()->for($regression, 'testPlan')->create(['name' => 'Build 4.8']);
        $passedItem = TestPlanItem::factory()->for($regression, 'testPlan')->create();
        $failedItem = TestPlanItem::factory()->for($regression, 'testPlan')->create();
        $failedItem->testCaseVersion()->update(['importance' => TestCaseImportance::High]);
        TestPlanItem::factory()->for($regression, 'testPlan')->create();

        Execution::factory()
            ->for($regression, 'testPlan')
            ->for($previousBuild, 'build')
            ->for($passedItem, 'testPlanItem')
            ->completed()
            ->create();
        Execution::factory()
            ->for($regression, 'testPlan')
            ->for($previousBuild, 'build')
            ->for($failedItem, 'testPlanItem')
            ->completed()
            ->create();
        Execution::factory()
            ->for($regression, 'testPlan')
            ->for($latestBuild, 'build')
            ->for($passedItem, 'testPlanItem')
            ->completed()
            ->create();
        $failed = Execution::factory()
            ->for($regression, 'testPlan')
            ->for($latestBuild, 'build')
            ->for($failedItem, 'testPlanItem')
            ->for($tester, 'tester')
            ->completed()
            ->create(['status' => ExecutionStatus::Failed]);
        ExecutionIssue::factory()->for($failed)->create(['issue_id' => 'JIRA-4821']);
        TesterAssignment::factory()
            ->for($passedItem, 'testPlanItem')
            ->for($latestBuild, 'build')
            ->for($tester, 'user')
            ->create();

        AuditEvent::factory()->create([
            'user_id' => $user->id,
            'action' => AuditAction::TestCaseVersionFrozen->value,
            'subject_type' => $fresh->getMorphClass(),
            'subject_id' => $fresh->id,
            'properties' => ['version' => 3],
            'created_at' => now()->subHours(2),
        ]);
        AuditEvent::factory()->create([
            'user_id' => $tester->id,
            'action' => AuditAction::TestCaseCreated->value,
            'subject_type' => $fresh->getMorphClass(),
            'subject_id' => $fresh->id,
            'properties' => ['test_suite_id' => $suite->id],
            'created_at' => now()->subMinutes(12),
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('overview/show')
                ->where('viewer', 'Maya Singh')
                ->where('overview.greeting', 'Good afternoon')
                ->where('overview.cases', 7)
                ->where('overview.cases_this_week', 6)
                ->where('overview.automated', 1)
                ->where('overview.active_runs', 2)
                ->where('overview.runs_in_progress', 1)
                ->where('overview.runs_blocked', 1)
                ->where('overview.pass_rate', 50)
                ->where('overview.latest_build.name', 'Build 4.8')
                ->where('overview.previous_build.name', 'Build 4.7')
                ->where('overview.previous_build.delta', -50)
                ->has('overview.runs', 2)
                ->where('overview.runs.0.name', 'Mobile Checkout · Sanity')
                ->where('overview.runs.0.external_id', 'CO-P1')
                ->where('overview.runs.1.name', 'Checkout · Regression')
                ->where('overview.runs.1.external_id', 'CO-P2')
                ->where('overview.runs.1.assignees', ['Sam Shah'])
                ->has('overview.needs_attention', 2)
                ->where(
                    'overview.needs_attention',
                    fn ($rows): bool => collect($rows)->contains(
                        fn ($row): bool => data_get($row, 'issue') === 'JIRA-4821'
                            && data_get($row, 'priority') === 'high',
                    ),
                )
                ->where(
                    'overview.needs_attention',
                    fn ($rows): bool => collect($rows)->contains(
                        fn ($row): bool => data_get($row, 'issue_note') === 'sandbox down',
                    ),
                )
                ->where('overview.activity.0.actor', 'Sam')
                ->where('overview.activity.0.verb', 'added a case to')
                ->where('overview.activity.0.token', $suite->name)
                ->where('overview.activity.0.when', '12 min ago')
                ->where('overview.activity.1.is_viewer', true)
                ->where('overview.activity.1.verb', 'froze version')
                ->where('overview.activity.1.token', 'v3')
                ->where('overview.activity.1.tail', 'of '.$fresh->fullExternalId())
                ->where('overview.activity.1.when', '2 hr ago'),
            );
    }

    public function test_an_outsider_is_forbidden(): void
    {
        $project = TestProject::factory()->restricted()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }
}
