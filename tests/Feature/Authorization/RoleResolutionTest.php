<?php

namespace Tests\Feature\Authorization;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_global_role_applies_to_public_test_projects()
    {
        $user = $this->userWithGlobalRole(Ability::ViewTestCases);
        $project = TestProject::factory()->create();

        $this->assertTrue($user->can(Ability::ViewTestCases->value, $project));
        $this->assertFalse($user->can(Ability::ManageTestCases->value, $project));
    }

    public function test_a_project_role_replaces_the_global_role()
    {
        $user = $this->userWithGlobalRole(Ability::ViewTestCases);
        $project = TestProject::factory()->create();

        $this->assignProjectRole($user, $project, Ability::ManageTestCases);

        $this->assertTrue($user->can(Ability::ManageTestCases->value, $project));
        $this->assertFalse($user->can(Ability::ViewTestCases->value, $project));
    }

    public function test_a_plan_role_replaces_the_project_role_for_that_plan_only()
    {
        $user = $this->userWithGlobalRole();
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();

        $this->assignProjectRole($user, $project, Ability::ExecuteTests);
        $this->assignPlanRole($user, $plan, Ability::ViewPlanMetrics);

        $this->assertTrue($user->can(Ability::ViewPlanMetrics->value, $plan));
        $this->assertFalse($user->can(Ability::ExecuteTests->value, $plan));
        $this->assertTrue($user->can(Ability::ExecuteTests->value, $project));
    }

    public function test_a_plan_falls_back_to_the_project_role()
    {
        $user = $this->userWithGlobalRole();
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();

        $this->assignProjectRole($user, $project, Ability::ExecuteTests);

        $this->assertTrue($user->can(Ability::ExecuteTests->value, $plan));
    }

    public function test_system_abilities_are_read_from_the_global_role_only()
    {
        $user = $this->userWithGlobalRole(Ability::ViewTestCases);
        $project = TestProject::factory()->create();

        $this->assignProjectRole($user, $project, Ability::ManageUsers);

        $this->assertFalse($user->can(Ability::ManageUsers->value));
        $this->assertFalse($user->can(Ability::ManageUsers->value, $project));
    }

    public function test_system_abilities_survive_a_narrower_project_role()
    {
        $user = $this->userWithGlobalRole(Ability::ManageUsers);
        $project = TestProject::factory()->create();

        $this->assignProjectRole($user, $project, Ability::ViewTestCases);

        $this->assertTrue($user->can(Ability::ManageUsers->value));
        $this->assertTrue($user->can(Ability::ManageUsers->value, $project));
    }

    public function test_a_restricted_project_denies_users_without_a_project_role()
    {
        $user = $this->userWithGlobalRole(Ability::ViewTestCases);
        $project = TestProject::factory()->restricted()->create();

        $this->assertFalse($user->can(Ability::ViewTestCases->value, $project));
    }

    public function test_a_restricted_project_allows_users_holding_a_project_role()
    {
        $user = $this->userWithGlobalRole();
        $project = TestProject::factory()->restricted()->create();

        $this->assignProjectRole($user, $project, Ability::ViewTestCases);

        $this->assertTrue($user->can(Ability::ViewTestCases->value, $project));
    }

    public function test_a_restricted_plan_denies_a_user_who_only_holds_a_project_role()
    {
        $user = $this->userWithGlobalRole();
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->restricted()->create();

        $this->assignProjectRole($user, $project, Ability::ExecuteTests);

        $this->assertTrue($user->can(Ability::ExecuteTests->value, $project));
        $this->assertFalse($user->can(Ability::ExecuteTests->value, $plan));
    }

    public function test_a_super_admin_role_grants_every_ability_everywhere()
    {
        $user = User::factory()->for(Role::factory()->superAdmin())->create();
        $project = TestProject::factory()->restricted()->create();

        foreach (Ability::cases() as $ability) {
            if ($ability->isRestriction()) {
                $this->assertFalse(
                    $user->can($ability->value, $project),
                    "Super admin was restricted by [{$ability->value}].",
                );

                continue;
            }

            $this->assertTrue(
                $user->can($ability->value, $project),
                "Super admin was denied [{$ability->value}].",
            );
        }
    }

    public function test_users_without_a_role_are_denied()
    {
        $user = User::factory()->create();
        $project = TestProject::factory()->create();

        $this->assertFalse($user->can(Ability::ViewTestCases->value, $project));
        $this->assertFalse($user->can(Ability::ManageUsers->value));
    }

    public function test_deactivated_users_are_denied()
    {
        $user = User::factory()
            ->for(Role::factory()->superAdmin())
            ->create(['is_active' => false]);

        $this->assertFalse($user->can(Ability::ManageUsers->value));
    }

    public function test_expired_users_are_denied()
    {
        $user = User::factory()
            ->for(Role::factory()->superAdmin())
            ->create(['expires_at' => now()->subDay()]);

        $this->assertFalse($user->can(Ability::ManageUsers->value));
    }

    public function test_users_remain_active_for_the_whole_of_their_expiry_date()
    {
        $user = User::factory()
            ->for(Role::factory()->superAdmin())
            ->create(['expires_at' => now()]);

        $this->assertTrue($user->can(Ability::ManageUsers->value));
    }

    private function userWithGlobalRole(Ability ...$abilities): User
    {
        return User::factory()
            ->for(Role::factory()->granting(...$abilities))
            ->create();
    }

    private function assignProjectRole(User $user, TestProject $project, Ability ...$abilities): void
    {
        $user->projectRoles()->attach(
            Role::factory()->granting(...$abilities)->create(),
            ['test_project_id' => $project->id],
        );
    }

    private function assignPlanRole(User $user, TestPlan $plan, Ability ...$abilities): void
    {
        $user->planRoles()->attach(
            Role::factory()->granting(...$abilities)->create(),
            ['test_plan_id' => $plan->id],
        );
    }
}
