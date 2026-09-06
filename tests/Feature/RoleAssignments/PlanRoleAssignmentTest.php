<?php

namespace Tests\Feature\RoleAssignments;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlanRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function assigner(TestPlan $plan): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $user->planRoles()->attach(
            Role::factory()->granting(Ability::AssignPlanRoles)->create(),
            ['test_plan_id' => $plan->id],
        );

        return $user;
    }

    public function test_an_assigner_gives_someone_a_plan_role(): void
    {
        $plan = TestPlan::factory()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $role = Role::factory()->granting(Ability::ExecuteTests)->create();

        $this->actingAs($this->assigner($plan))
            ->post(route('plans.members.store', $plan), [
                'user_email' => $member->email,
                'role_id' => $role->id,
            ])
            ->assertRedirect(route('plans.members.index', $plan));

        $this->assertDatabaseHas('test_plan_user', [
            'user_id' => $member->id,
            'test_plan_id' => $plan->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * A plan role is granted per plan, so holding one for a sibling plan in the
     * same project confers nothing here.
     */
    public function test_a_role_for_one_plan_does_not_reach_another(): void
    {
        $project = TestProject::factory()->create();
        $granted = TestPlan::factory()->for($project)->create();
        $other = TestPlan::factory()->for($project)->create();

        $member = User::factory()->for(Role::factory()->granting())->create();
        $role = Role::factory()->granting(Ability::ExecuteTests)->create();

        $this->actingAs($this->assigner($granted))
            ->post(route('plans.members.store', $other), [
                'user_email' => $member->email,
                'role_id' => $role->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('test_plan_user', 1);
    }

    /**
     * The same deadlock as on a private project: a restricted plan resolves to
     * no role for anyone unassigned, so an administrator has to be able to seed
     * the first one.
     */
    public function test_an_administrator_can_seed_the_first_role_on_a_private_plan(): void
    {
        $plan = TestPlan::factory()->restricted()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $role = Role::factory()->granting(Ability::ExecuteTests)->create();

        $administrator = User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestProjects))
            ->create();

        $this->assertFalse(
            $administrator->can(Ability::AssignPlanRoles->value, $plan),
            'The scoped ability alone should not reach into a private plan.',
        );

        $this->actingAs($administrator)
            ->post(route('plans.members.store', $plan), [
                'user_email' => $member->email,
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('test_plan_user', 1);
    }

    public function test_an_assigner_removes_a_plan_role(): void
    {
        $plan = TestPlan::factory()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $member->planRoles()->attach(
            Role::factory()->granting(Ability::ExecuteTests)->create(),
            ['test_plan_id' => $plan->id],
        );

        $this->actingAs($this->assigner($plan))
            ->delete(route('plans.members.destroy', [$plan, $member]))
            ->assertRedirect(route('plans.members.index', $plan));

        $this->assertSame(0, $member->planRoles()->count());
    }

    /**
     * A project role granting `assign_plan_roles` reaches the project's plans,
     * because a plan with no plan role falls back to the project role.
     */
    public function test_a_project_assigner_can_manage_a_public_plans_members(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();

        $user = User::factory()->for(Role::factory()->granting())->create();
        $user->projectRoles()->attach(
            Role::factory()->granting(Ability::AssignPlanRoles)->create(),
            ['test_project_id' => $project->id],
        );

        $this->actingAs($user)
            ->get(route('plans.members.index', $plan))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('test-plans/members')
                ->where('plan.id', $plan->id)
                ->where('project.id', $project->id),
            );
    }

    public function test_deleting_a_plan_removes_its_assignments(): void
    {
        $plan = TestPlan::factory()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $member->planRoles()->attach(
            Role::factory()->granting(Ability::ExecuteTests)->create(),
            ['test_plan_id' => $plan->id],
        );

        $plan->delete();

        $this->assertDatabaseCount('test_plan_user', 0);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $plan = TestPlan::factory()->create();

        $this->get(route('plans.members.index', $plan))
            ->assertRedirect(route('login'));
    }
}
