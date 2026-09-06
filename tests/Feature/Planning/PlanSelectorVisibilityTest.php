<?php

namespace Tests\Feature\Planning;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlanSelectorVisibilityTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $project = TestProject::factory()->create();

        $this->get(route('plan-selector.index', $project))->assertRedirect(route('login'));
    }

    public function test_viewing_cases_is_not_enough_to_open_the_selector(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('plan-selector.index', $project))
            ->assertForbidden();
    }

    public function test_a_tester_sees_only_the_plans_they_can_run_or_inspect(): void
    {
        $project = TestProject::factory()->create();
        $runnable = TestPlan::factory()->for($project)->create(['name' => 'Sprint']);
        TestPlan::factory()->for($project)->restricted()->create(['name' => 'Secret']);
        $user = $this->userWhoCan($project, Ability::ExecuteTests);

        $this->actingAs($user)
            ->get(route('plan-selector.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('plan-selector/index')
                ->has('plans', 1)
                ->where('plans.0.name', 'Sprint')
                ->where('plans.0.can_execute', true)
                ->where('currentProject.can.selectPlans', true)
            );
    }

    public function test_a_plan_role_reaches_a_private_plan_the_project_role_cannot(): void
    {
        $project = TestProject::factory()->create();
        $private = TestPlan::factory()->for($project)->restricted()->create(['name' => 'Private']);
        $user = User::factory()->for(Role::factory()->granting())->create();
        $this->assignPlanRole($user, $private, Ability::ExecuteTests);

        $this->actingAs($user)
            ->get(route('plan-selector.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('plans', 1)
                ->where('plans.0.name', 'Private')
            );
    }

    /**
     * The bulk resolver behind the selector and the per-plan gate must agree,
     * or a listed plan 403s when opened — the same trap as the project index.
     */
    public function test_the_bulk_resolver_agrees_with_the_single_plan_gate(): void
    {
        $project = TestProject::factory()->create();
        $public = TestPlan::factory()->for($project)->create();
        $restricted = TestPlan::factory()->for($project)->restricted()->create();
        $assigned = TestPlan::factory()->for($project)->restricted()->create();

        $users = [
            'granted globally' => User::factory()
                ->for(Role::factory()->granting(Ability::ExecuteTests))
                ->create(),
            'granted nothing' => User::factory()
                ->for(Role::factory()->granting())
                ->create(),
            'super admin' => User::factory()
                ->for(Role::factory()->superAdmin())
                ->create(),
            'expired' => User::factory()
                ->for(Role::factory()->granting(Ability::ExecuteTests))
                ->create(['expires_at' => now()->subDay()]),
        ];

        foreach ($users as $user) {
            $user->planRoles()->attach(
                Role::factory()->granting(Ability::ExecuteTests)->create(),
                ['test_plan_id' => $assigned->id],
            );
        }

        $resolver = app(RoleResolver::class);
        $plans = $project->testPlans()->orderBy('id')->get();

        foreach ($users as $description => $user) {
            $bulk = $resolver
                ->plansAllowing($user->fresh(), Ability::ExecuteTests, $plans)
                ->modelKeys();

            foreach ([$public, $restricted, $assigned] as $plan) {
                $this->assertSame(
                    $resolver->allows($user->fresh(), Ability::ExecuteTests, $plan),
                    in_array($plan->getKey(), $bulk, true),
                    "Bulk and single resolution disagree for a user {$description} on plan {$plan->id}.",
                );
            }
        }
    }
}
