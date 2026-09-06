<?php

namespace Tests\Feature\Roles;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_role_manager_sees_the_role_list()
    {
        Role::factory()->create(['name' => 'Senior Tester']);

        $this->actingAs($this->roleManager())
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Senior Tester');
    }

    public function test_the_role_list_needs_the_manage_roles_ability()
    {
        $actor = User::factory()->for(Role::factory(), 'role')->create();

        $this->actingAs($actor)->get(route('roles.index'))->assertForbidden();
    }

    /**
     * `manage_roles` is a system ability, so a project role granting it must
     * not open role administration.
     */
    public function test_a_project_role_cannot_grant_access_to_role_administration()
    {
        $actor = User::factory()->for(Role::factory(), 'role')->create();
        $actor->projectRoles()->attach(
            Role::factory()->granting(Ability::ManageRoles)->create(),
            ['test_project_id' => TestProject::factory()->create()->id],
        );

        $this->actingAs($actor)->get(route('roles.index'))->assertForbidden();
    }

    public function test_a_role_is_created_with_the_ticked_abilities()
    {
        $this->actingAs($this->roleManager())->post(route('roles.store'), [
            'name' => 'Read Only',
            'description' => 'Can look but not touch.',
            'abilities' => [
                Ability::ViewTestCases->value,
                Ability::ViewRequirements->value,
            ],
        ])->assertRedirect(route('roles.index'));

        $role = Role::query()->where('name', 'Read Only')->sole();

        $this->assertTrue($role->grants(Ability::ViewTestCases));
        $this->assertTrue($role->grants(Ability::ViewRequirements));
        $this->assertFalse($role->grants(Ability::ManageTestCases));
    }

    /**
     * The safe default: a role created by accident cannot widen anyone's
     * access.
     */
    public function test_a_role_created_with_nothing_ticked_grants_nothing()
    {
        $this->actingAs($this->roleManager())->post(route('roles.store'), [
            'name' => 'Empty',
        ])->assertRedirect(route('roles.index'));

        $this->assertCount(0, Role::query()->where('name', 'Empty')->sole()->abilities);
    }

    public function test_creating_a_role_rejects_a_duplicate_name()
    {
        Role::factory()->create(['name' => 'Taken']);

        $this->actingAs($this->roleManager())
            ->post(route('roles.store'), ['name' => 'Taken'])
            ->assertSessionHasErrors('name');
    }

    /**
     * An unknown value would be stored and then throw on the next read, when
     * `AsEnumCollection` fails to decode it.
     */
    public function test_an_unknown_ability_is_rejected()
    {
        $this->actingAs($this->roleManager())->post(route('roles.store'), [
            'name' => 'Bogus',
            'abilities' => ['not_a_real_ability'],
        ])->assertSessionHasErrors('abilities.0');

        $this->assertDatabaseMissing('roles', ['name' => 'Bogus']);
    }

    public function test_editing_a_role_replaces_its_abilities()
    {
        $role = Role::factory()->granting(Ability::ViewTestCases, Ability::ManageTestCases)->create();

        $this->actingAs($this->roleManager())->put(route('roles.update', $role), [
            'name' => $role->name,
            'abilities' => [Ability::ViewTestCases->value],
        ])->assertRedirect(route('roles.index'));

        $role->refresh();

        $this->assertTrue($role->grants(Ability::ViewTestCases));
        $this->assertFalse($role->grants(Ability::ManageTestCases));
    }

    /**
     * A role edit reaches every holder at once, which is the whole reason this
     * screen is dangerous.
     */
    public function test_editing_a_role_changes_what_its_holders_can_do()
    {
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();
        $holder = User::factory()->create(['role_id' => $role->id]);
        $project = TestProject::factory()->create();

        $this->assertTrue($holder->can(Ability::ViewTestCases->value, $project));

        $this->actingAs($this->roleManager())->put(route('roles.update', $role), [
            'name' => $role->name,
            'abilities' => [],
        ]);

        $this->assertFalse($holder->fresh()?->can(Ability::ViewTestCases->value, $project));
    }

    /**
     * The flag overrides the ability list, so the two must not be confused.
     */
    public function test_an_unrestricted_role_passes_checks_it_does_not_list()
    {
        $role = Role::factory()->create();
        $holder = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($this->roleManager())->put(route('roles.update', $role), [
            'name' => $role->name,
            'is_super_admin' => '1',
            'abilities' => [],
        ])->assertRedirect(route('roles.index'));

        $this->assertTrue($holder->fresh()?->can(Ability::ManageUsers->value));
    }

    /**
     * The sidebar's Roles item is gated on this shared prop.
     */
    public function test_the_shared_props_report_whether_roles_can_be_managed()
    {
        $this->actingAs($this->roleManager())
            ->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page->where('auth.can.manageRoles', true));

        $this->actingAs(User::factory()->for(Role::factory(), 'role')->create())
            ->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page->where('auth.can.manageRoles', false));
    }

    private function roleManager(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageRoles, Ability::AssignGlobalRoles), 'role')
            ->create();
    }
}
