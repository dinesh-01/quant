<?php

namespace Tests\Feature\Users;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `manage_users` and `assign_global_roles` are separate system abilities, so
 * editing an account and promoting one are separately authorized.
 */
class GlobalRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_role_assigner_sets_a_global_role()
    {
        $actor = $this->roleAssigner();
        $subject = User::factory()->create();
        $role = Role::factory()->create();

        $this->actingAs($actor)->put(route('users.update', $subject), [
            'name' => $subject->name,
            'email' => $subject->email,
            'is_active' => '1',
            'role_id' => $role->id,
        ])->assertRedirect(route('users.index'));

        $this->assertSame($role->id, $subject->refresh()->role_id);
    }

    public function test_managing_users_alone_does_not_permit_changing_a_role()
    {
        $actor = $this->userManager();
        $subject = User::factory()->create();
        $role = Role::factory()->create();

        $this->actingAs($actor)->put(route('users.update', $subject), [
            'name' => $subject->name,
            'email' => $subject->email,
            'is_active' => '1',
            'role_id' => $role->id,
        ])->assertForbidden();

        $this->assertNull($subject->refresh()->role_id);
    }

    /**
     * The edit form leaves the role field out for an actor who cannot assign
     * roles, so an omitted field has to mean "unchanged" rather than "none" —
     * otherwise every ordinary edit of a user who has a role would be refused.
     */
    public function test_an_omitted_role_field_leaves_an_existing_role_alone()
    {
        $actor = $this->userManager();
        $role = Role::factory()->create();
        $subject = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($actor)->put(route('users.update', $subject), [
            'name' => 'Renamed Person',
            'email' => $subject->email,
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $subject->refresh();

        $this->assertSame('Renamed Person', $subject->name);
        $this->assertSame($role->id, $subject->role_id);
    }

    /**
     * A field that is present but empty is an explicit choice, and must be
     * distinguished from one that was never rendered.
     */
    public function test_an_empty_role_field_removes_the_role()
    {
        $actor = $this->roleAssigner();
        $subject = User::factory()->create(['role_id' => Role::factory()->create()->id]);

        $this->actingAs($actor)->put(route('users.update', $subject), [
            'name' => $subject->name,
            'email' => $subject->email,
            'is_active' => '1',
            'role_id' => '',
        ])->assertRedirect(route('users.index'));

        $this->assertNull($subject->refresh()->role_id);
    }

    public function test_creating_an_account_with_a_role_needs_the_assign_ability()
    {
        $actor = $this->userManager();

        $this->actingAs($actor)->post(route('users.store'), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'role_id' => Role::factory()->create()->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'grace@example.com']);
    }

    /**
     * Unlike the project and plan member screens, which refuse it: the
     * `is_super_admin` flag is only honoured on a global role, so this is the
     * one place granting it does anything.
     */
    public function test_a_super_admin_role_may_be_granted_globally()
    {
        $actor = $this->roleAssigner();
        $subject = User::factory()->create();
        $superAdmin = Role::factory()->superAdmin()->create();

        $this->actingAs($actor)->put(route('users.update', $subject), [
            'name' => $subject->name,
            'email' => $subject->email,
            'is_active' => '1',
            'role_id' => $superAdmin->id,
        ])->assertRedirect(route('users.index'));

        $this->assertTrue($subject->refresh()->can(Ability::ManageUsers->value));
    }

    private function userManager(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageUsers), 'role')
            ->create();
    }

    private function roleAssigner(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageUsers, Ability::AssignGlobalRoles), 'role')
            ->create();
    }
}
