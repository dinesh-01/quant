<?php

namespace Tests\Feature\Issues;

use App\Enums\Ability;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use App\Models\TestPlan;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ProjectIssuesTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_issues_are_limited_to_the_current_project(): void
    {
        $project = TestProject::factory()->create();
        $other = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $otherPlan = TestPlan::factory()->for($other)->create();
        $execution = Execution::factory()->for($plan, 'testPlan')->create();
        $foreign = Execution::factory()->for($otherPlan, 'testPlan')->create();

        ExecutionIssue::factory()->for($execution)->create(['issue_id' => 'CO-1']);
        ExecutionIssue::factory()->for($foreign)->create(['issue_id' => 'XX-9']);

        $user = $this->userWhoCan($project, Ability::ViewIssueTrackers);

        $this->actingAs($user)
            ->get(route('issues.index', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('issues/index')
                ->has('issues', 1)
                ->where('issues.0.issue_id', 'CO-1')
                ->has('issues.0.suite'),
            );
    }

    public function test_view_executions_is_enough_to_open_the_list(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewExecutions);

        $this->actingAs($user)
            ->get(route('issues.index', $project))
            ->assertOk();
    }

    public function test_a_stranger_is_forbidden(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('issues.index', $project))
            ->assertForbidden();
    }
}
