<?php

namespace Tests\Feature\TestPlans;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TestPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user whose global role grants nothing, holding `create_test_plans` for
     * one project only.
     */
    private function planner(TestProject $project): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $user->projectRoles()->attach(
            Role::factory()->granting(Ability::CreateTestPlans)->create(),
            ['test_project_id' => $project->id],
        );

        return $user;
    }

    public function test_a_planner_sees_every_plan_in_the_project(): void
    {
        $project = TestProject::factory()->create();
        TestPlan::factory()->for($project)->create(['name' => 'Regression']);
        TestPlan::factory()->for($project)->closed()->create(['name' => 'Release 1']);
        TestPlan::factory()->create(['name' => 'Another project plan']);

        $this->actingAs($this->planner($project))
            ->get(route('plans.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('test-plans/index')
                ->has('plans', 2)
                ->where('plans.0.name', 'Regression')
                ->where('plans.1.name', 'Release 1')
                ->where('plans.1.is_open', false),
            );
    }

    public function test_the_plan_list_requires_the_plan_ability_on_the_project(): void
    {
        $project = TestProject::factory()->create();

        $user = User::factory()
            ->for(Role::factory()->granting(Ability::ViewTestCases))
            ->create();

        $this->actingAs($user)->get(route('plans.index', $project))->assertForbidden();
        $this->actingAs($user)->get(route('plans.create', $project))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $project = TestProject::factory()->create();

        $this->get(route('plans.index', $project))->assertRedirect(route('login'));
    }

    public function test_a_planner_creates_a_plan(): void
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->planner($project))
            ->post(route('plans.store', $project), [
                'name' => 'Release 4.2 regression',
                'description' => 'Full pass before release.',
                'is_open' => '1',
                'is_active' => '1',
                'is_public' => '1',
            ])
            ->assertRedirect(route('plans.index', $project));

        $plan = TestPlan::query()->sole();

        $this->assertSame('Release 4.2 regression', $plan->name);
        $this->assertSame($project->id, $plan->test_project_id);
        $this->assertTrue($plan->is_open);
    }

    public function test_a_new_plan_is_open_unless_said_otherwise(): void
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->planner($project))
            ->post(route('plans.store', $project), ['name' => 'Closed from the start'])
            ->assertRedirect();

        $this->assertFalse(
            TestPlan::query()->sole()->is_open,
            'An unchecked switch means closed, matching every other boolean on these forms.',
        );
    }

    public function test_an_empty_description_is_stored_as_null(): void
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->planner($project))
            ->post(route('plans.store', $project), ['name' => 'Regression', 'description' => ''])
            ->assertRedirect();

        $this->assertNull(TestPlan::query()->sole()->description);
    }

    /**
     * The unique index is per project, so the same name in two projects is
     * fine and a repeat within one is not.
     */
    public function test_a_plan_name_is_unique_within_its_project_only(): void
    {
        $project = TestProject::factory()->create();
        $other = TestProject::factory()->create();
        TestPlan::factory()->for($project)->create(['name' => 'Regression']);

        $this->actingAs($this->planner($project))
            ->post(route('plans.store', $project), ['name' => 'Regression'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->planner($other))
            ->post(route('plans.store', $other), ['name' => 'Regression'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, TestPlan::query()->count());
    }

    public function test_creating_a_plan_requires_the_ability_on_that_project(): void
    {
        $project = TestProject::factory()->create();
        $other = TestProject::factory()->create();

        $this->actingAs($this->planner($other))
            ->post(route('plans.store', $project), ['name' => 'Regression'])
            ->assertForbidden();

        $this->assertDatabaseCount('test_plans', 0);
    }

    public function test_a_planner_renames_a_plan_and_closes_it(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Old']);

        $this->actingAs($this->planner($project))
            ->put(route('plans.update', $plan), [
                'name' => 'New',
                'is_active' => '1',
                'is_public' => '1',
            ])
            ->assertRedirect(route('plans.index', $project));

        $plan->refresh();

        $this->assertSame('New', $plan->name);
        $this->assertFalse($plan->is_open, 'Omitting is_open should close the plan.');
    }

    public function test_a_plan_keeps_its_own_name_when_edited(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Regression']);

        $this->actingAs($this->planner($project))
            ->put(route('plans.update', $plan), ['name' => 'Regression', 'is_open' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Regression', $plan->refresh()->name);
    }

    /**
     * Ability resolution is plan role, then project role, then global role, so
     * a role held for one plan governs that plan and nothing else. Legacy
     * checked plan editing against the project, which made plan roles unable
     * to affect it at all.
     */
    public function test_a_plan_role_governs_that_plan_alone(): void
    {
        $project = TestProject::factory()->create();
        $granted = TestPlan::factory()->for($project)->create();
        $ungranted = TestPlan::factory()->for($project)->create();

        $user = User::factory()->for(Role::factory()->granting())->create();
        $user->planRoles()->attach(
            Role::factory()->granting(Ability::CreateTestPlans)->create(),
            ['test_plan_id' => $granted->id],
        );

        $this->actingAs($user)
            ->put(route('plans.update', $granted), ['name' => 'Renamed', 'is_open' => '1'])
            ->assertRedirect();

        $this->assertSame('Renamed', $granted->refresh()->name);

        $this->actingAs($user)
            ->put(route('plans.update', $ungranted), ['name' => 'Renamed too'])
            ->assertForbidden();
    }

    /**
     * The most specific role replaces the less specific one rather than adding
     * to it, so a plan role granting nothing withholds what the project role
     * would otherwise have given.
     */
    public function test_a_plan_role_can_withhold_what_the_project_role_grants(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();

        $user = $this->planner($project);
        $user->planRoles()->attach(
            Role::factory()->granting()->create(),
            ['test_plan_id' => $plan->id],
        );

        $this->actingAs($user)
            ->put(route('plans.update', $plan), ['name' => 'Renamed'])
            ->assertForbidden();
    }

    public function test_editing_a_plan_from_another_project_is_refused(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->create();

        $this->actingAs($this->planner($project))
            ->get(route('plans.edit', $plan))
            ->assertForbidden();
    }

    public function test_the_edit_page_reports_the_plan_and_its_project(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->closed()->create();

        $this->actingAs($this->planner($project))
            ->get(route('plans.edit', $plan))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('test-plans/edit')
                ->where('plan.id', $plan->id)
                ->where('plan.is_open', false)
                ->where('project.id', $project->id),
            );
    }

    public function test_deleting_a_plan_requires_its_name_to_be_typed(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Regression']);

        $this->actingAs($this->planner($project))
            ->delete(route('plans.destroy', $plan), ['confirm_name' => 'regression'])
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseCount('test_plans', 1);
    }

    public function test_deleting_a_plan_leaves_the_project_and_its_cases_alone(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Regression']);

        $planner = $this->planner($project);
        $planner->planRoles()->attach(
            Role::factory()->granting(Ability::CreateTestPlans)->create(),
            ['test_plan_id' => $plan->id],
        );

        $this->actingAs($planner)
            ->delete(route('plans.destroy', $plan), ['confirm_name' => 'Regression'])
            ->assertRedirect(route('plans.index', $project));

        $this->assertDatabaseCount('test_plans', 0);
        $this->assertDatabaseCount('test_plan_user', 0);
        $this->assertDatabaseHas('test_projects', ['id' => $project->id]);
    }

    /**
     * The project group in the sidebar is built from these, so a user without
     * the ability must not be offered the plan screens at all.
     */
    public function test_the_shared_project_prop_reports_the_plan_ability(): void
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->planner($project))
            ->get(route('plans.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentProject.can.manageTestPlans', true)
                ->where('currentProject.can.viewSpecification', false)
                ->where('currentProject.id', $project->id),
            );
    }
}
