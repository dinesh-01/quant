<?php

namespace Tests\Feature\Roles;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role editing is the widest-reaching write in the application, so the things
 * it must never be allowed to do are covered on their own.
 */
class RoleInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_last_role_that_can_assign_global_roles_cannot_lose_that_ability()
    {
        $actor = $this->roleManager();

        $response = $this->actingAs($actor)->put(route('roles.update', $actor->role), [
            'name' => $actor->role?->name,
            'abilities' => [Ability::ManageRoles->value],
        ]);

        $response->assertSessionHasErrors('abilities');
        $this->assertTrue($actor->role?->fresh()?->grants(Ability::AssignGlobalRoles));
    }

    public function test_clearing_the_unrestricted_flag_is_refused_when_it_is_the_only_way_in()
    {
        $role = Role::factory()->superAdmin()->create();
        User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs(User::factory()->create(['role_id' => $role->id]))
            ->put(route('roles.update', $role), [
                'name' => $role->name,
                'abilities' => [],
            ]);

        $response->assertSessionHasErrors('abilities');
        $this->assertTrue($role->fresh()?->is_super_admin);
    }

    public function test_a_role_may_lose_the_ability_once_another_role_grants_it()
    {
        $actor = $this->roleManager();
        $spare = Role::factory()->granting(Ability::AssignGlobalRoles)->create();
        User::factory()->create(['role_id' => $spare->id]);

        $this->actingAs($actor)->put(route('roles.update', $actor->role), [
            'name' => $actor->role?->name,
            'abilities' => [Ability::ManageRoles->value],
        ])->assertRedirect(route('roles.index'));

        $this->assertFalse($actor->role?->fresh()?->grants(Ability::AssignGlobalRoles));
    }

    /**
     * A role nobody holds does not satisfy the invariant: the ability has to be
     * reachable by an actual account.
     */
    public function test_a_role_granting_the_ability_but_held_by_nobody_does_not_count()
    {
        $actor = $this->roleManager();
        Role::factory()->granting(Ability::AssignGlobalRoles)->create();

        $this->actingAs($actor)->put(route('roles.update', $actor->role), [
            'name' => $actor->role?->name,
            'abilities' => [Ability::ManageRoles->value],
        ])->assertSessionHasErrors('abilities');
    }

    public function test_exactly_one_role_is_the_default()
    {
        $existing = Role::factory()->asDefault()->create();

        $this->actingAs($this->roleManager())->post(route('roles.store'), [
            'name' => 'New Default',
            'is_default' => '1',
        ])->assertRedirect(route('roles.index'));

        $this->assertFalse($existing->fresh()?->is_default);
        $this->assertSame(1, Role::query()->where('is_default', true)->count());
    }

    public function test_the_only_default_role_cannot_stop_being_the_default()
    {
        $role = Role::factory()->asDefault()->create();

        $response = $this->actingAs($this->roleManager())->put(route('roles.update', $role), [
            'name' => $role->name,
        ]);

        $response->assertSessionHasErrors('is_default');
        $this->assertTrue($role->fresh()?->is_default);
    }

    /**
     * `users.role_id` is nullOnDelete, so without this the holders would
     * quietly drop to no global role and be denied everything.
     */
    public function test_a_role_held_as_a_global_role_cannot_be_deleted()
    {
        $role = Role::factory()->create();
        User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($this->roleManager())
            ->delete(route('roles.destroy', $role), ['confirm_name' => $role->name]);

        $response->assertSessionHasErrors('confirm_name');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    /**
     * The project and plan pivots cascade, so the assignments would vanish
     * with no record of what they granted.
     */
    public function test_a_role_assigned_to_a_project_cannot_be_deleted()
    {
        $role = Role::factory()->create();
        User::factory()->create()->projectRoles()->attach($role, [
            'test_project_id' => TestProject::factory()->create()->id,
        ]);

        $this->actingAs($this->roleManager())
            ->delete(route('roles.destroy', $role), ['confirm_name' => $role->name])
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_a_role_assigned_to_a_plan_cannot_be_deleted()
    {
        $role = Role::factory()->create();
        $plan = TestPlan::factory()->for(TestProject::factory(), 'testProject')->create();
        User::factory()->create()->planRoles()->attach($role, ['test_plan_id' => $plan->id]);

        $this->actingAs($this->roleManager())
            ->delete(route('roles.destroy', $role), ['confirm_name' => $role->name])
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_an_unused_role_is_deleted_once_its_name_is_typed()
    {
        $role = Role::factory()->create();

        $this->actingAs($this->roleManager())
            ->delete(route('roles.destroy', $role), ['confirm_name' => $role->name])
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_deleting_a_role_needs_its_name_typed_exactly()
    {
        $role = Role::factory()->create(['name' => 'Spare Role']);

        $this->actingAs($this->roleManager())
            ->delete(route('roles.destroy', $role), ['confirm_name' => 'spare role'])
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    /**
     * Load-bearing for registration even before anyone holds it, so the
     * in-use check would not catch this on a fresh installation.
     */
    public function test_the_default_role_cannot_be_deleted_even_when_unused()
    {
        $role = Role::factory()->asDefault()->create();

        $this->actingAs($this->roleManager())
            ->delete(route('roles.destroy', $role), ['confirm_name' => $role->name])
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    /**
     * The guard has to fire on the change that removes the standing, not on
     * the state. Judged on state alone, an installation with no usable
     * assigner has every unrelated role edit refused — and refused with a
     * message about assigning global roles, on a role that never granted it,
     * leaving no way to edit out of the state from the interface.
     */
    public function test_a_role_that_never_granted_administration_can_be_edited_when_nobody_can_administer()
    {
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $actor = User::factory()
            ->for(Role::factory()->granting(Ability::ManageRoles), 'role')
            ->create();

        $this->actingAs($actor)
            ->put(route('roles.update', $role), [
                'name' => $role->name,
                'abilities' => [Ability::ViewTestCases->value, Ability::ManageTestCases->value],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($role->refresh()->grants(Ability::ManageTestCases));
    }

    private function roleManager(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageRoles, Ability::AssignGlobalRoles), 'role')
            ->create();
    }
}
