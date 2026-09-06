<?php

namespace Tests\Feature\RoleAssignments;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProjectRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function assigner(TestProject $project): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $user->projectRoles()->attach(
            Role::factory()->granting(Ability::AssignProjectRoles)->create(),
            ['test_project_id' => $project->id],
        );

        return $user;
    }

    private function administrator(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestProjects))
            ->create();
    }

    public function test_an_assigner_gives_someone_a_project_role(): void
    {
        $project = TestProject::factory()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $this->actingAs($this->assigner($project))
            ->post(route('projects.members.store', $project), [
                'user_email' => $member->email,
                'role_id' => $role->id,
            ])
            ->assertRedirect(route('projects.members.index', $project));

        $this->assertDatabaseHas('test_project_user', [
            'user_id' => $member->id,
            'test_project_id' => $project->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * One role per user per project, so assigning again replaces rather than
     * adding — which the pivot's composite primary key would reject anyway.
     */
    public function test_assigning_again_replaces_the_existing_role(): void
    {
        $project = TestProject::factory()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $first = Role::factory()->granting(Ability::ViewTestCases)->create();
        $second = Role::factory()->granting(Ability::ManageTestCases)->create();

        $member->projectRoles()->attach($first, ['test_project_id' => $project->id]);

        $this->actingAs($this->assigner($project))
            ->post(route('projects.members.store', $project), [
                'user_email' => $member->email,
                'role_id' => $second->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, $member->projectRoles()->count());
        $this->assertDatabaseHas('test_project_user', [
            'user_id' => $member->id,
            'test_project_id' => $project->id,
            'role_id' => $second->id,
        ]);
    }

    public function test_an_assigner_removes_a_project_role(): void
    {
        $project = TestProject::factory()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $member->projectRoles()->attach(
            Role::factory()->granting(Ability::ViewTestCases)->create(),
            ['test_project_id' => $project->id],
        );

        $this->actingAs($this->assigner($project))
            ->delete(route('projects.members.destroy', [$project, $member]))
            ->assertRedirect(route('projects.members.index', $project));

        $this->assertDatabaseCount('test_project_user', 1);
        $this->assertSame(0, $member->projectRoles()->count());
    }

    public function test_someone_without_the_ability_cannot_reach_or_change_members(): void
    {
        $project = TestProject::factory()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $outsider = User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestCases))
            ->create();

        $this->actingAs($outsider)
            ->get(route('projects.members.index', $project))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('projects.members.store', $project), [
                'user_email' => $member->email,
                'role_id' => $role->id,
            ])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->delete(route('projects.members.destroy', [$project, $member]))
            ->assertForbidden();

        $this->assertDatabaseCount('test_project_user', 0);
    }

    /**
     * The deadlock this screen exists to escape.
     *
     * On a restricted project, ability resolution returns no role at all for
     * anyone not already assigned, and assignment is the only way to become
     * assigned. Without the administrator clause in
     * RoleResolver::mayAssignRolesIn(), the first role on a private project
     * could only ever be granted by a super admin — so an administrator who
     * made a project private would have locked themselves out of fixing it.
     */
    public function test_an_administrator_can_seed_the_first_role_on_a_private_project(): void
    {
        $project = TestProject::factory()->restricted()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $administrator = $this->administrator();

        $this->assertFalse(
            $administrator->can(Ability::AssignProjectRoles->value, $project),
            'The scoped ability alone should not reach into a private project.',
        );

        $this->actingAs($administrator)
            ->get(route('projects.members.index', $project))
            ->assertOk();

        $this->actingAs($administrator)
            ->post(route('projects.members.store', $project), [
                'user_email' => $member->email,
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('test_project_user', [
            'user_id' => $member->id,
            'test_project_id' => $project->id,
        ]);
    }

    /**
     * Swapping your own role for one without the assign ability is how you
     * strand yourself, so a non-administrator is refused both edits to their
     * own row.
     */
    public function test_an_assigner_cannot_change_or_remove_their_own_role(): void
    {
        $project = TestProject::factory()->restricted()->create();
        $assigner = $this->assigner($project);
        $harmless = Role::factory()->granting()->create();

        $this->actingAs($assigner)
            ->post(route('projects.members.store', $project), [
                'user_email' => $assigner->email,
                'role_id' => $harmless->id,
            ])
            ->assertSessionHasErrors('user_email');

        $this->actingAs($assigner)
            ->delete(route('projects.members.destroy', [$project, $assigner]))
            ->assertSessionHasErrors('user_email');

        $this->assertSame(1, $assigner->projectRoles()->count());
    }

    /**
     * An administrator is exempt, because they can always restore their own
     * access — and without the exemption they could strand themselves with a
     * self-assignment they were then forbidden to remove.
     */
    public function test_an_administrator_may_change_their_own_role(): void
    {
        $project = TestProject::factory()->restricted()->create();
        $administrator = $this->administrator();
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $this->actingAs($administrator)
            ->post(route('projects.members.store', $project), [
                'user_email' => $administrator->email,
                'role_id' => $role->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($administrator)
            ->delete(route('projects.members.destroy', [$project, $administrator]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $administrator->projectRoles()->count());
    }

    /**
     * `is_super_admin` is only honoured on a global role, so offering it as a
     * project role would promise powers the resolver ignores.
     */
    public function test_a_super_admin_role_cannot_be_given_for_a_single_project(): void
    {
        $project = TestProject::factory()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $superAdmin = Role::factory()->superAdmin()->create();

        $this->actingAs($this->assigner($project))
            ->post(route('projects.members.store', $project), [
                'user_email' => $member->email,
                'role_id' => $superAdmin->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseCount('test_project_user', 1);
    }

    public function test_an_unknown_email_is_rejected(): void
    {
        $project = TestProject::factory()->create();
        $role = Role::factory()->granting(Ability::ViewTestCases)->create();

        $this->actingAs($this->assigner($project))
            ->post(route('projects.members.store', $project), [
                'user_email' => 'nobody@example.com',
                'role_id' => $role->id,
            ])
            ->assertSessionHasErrors('user_email');
    }

    public function test_the_page_lists_members_with_their_role_and_marks_the_actor(): void
    {
        $project = TestProject::factory()->create();
        $assigner = $this->assigner($project);

        $member = User::factory()->for(Role::factory()->granting())->create(['name' => 'Aaron']);
        $member->projectRoles()->attach(
            Role::factory()->granting(Ability::ViewTestCases)->create(['name' => 'Reader']),
            ['test_project_id' => $project->id],
        );

        $this->actingAs($assigner)
            ->get(route('projects.members.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('test-projects/members')
                ->has('members', 2)
                ->where('members.0.name', 'Aaron')
                ->where('members.0.role_name', 'Reader')
                ->where('members.0.is_self', false)
                ->where('isRestricted', false)
                ->where('currentProject.can.manageMembers', true),
            );
    }

    /**
     * A super admin role must not appear in the dropdown, since assigning it
     * here is refused.
     */
    public function test_the_assignable_roles_exclude_super_admin_roles(): void
    {
        $project = TestProject::factory()->create();
        Role::factory()->superAdmin()->create(['name' => 'Root']);
        Role::factory()->granting(Ability::ViewTestCases)->create(['name' => 'Reader']);

        $this->actingAs($this->assigner($project))
            ->get(route('projects.members.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('roles', fn (Collection|array $roles): bool => collect($roles)
                    ->pluck('name')
                    ->doesntContain('Root'),
                ),
            );
    }

    public function test_deleting_a_user_removes_their_assignments(): void
    {
        $project = TestProject::factory()->create();
        $member = User::factory()->for(Role::factory()->granting())->create();
        $member->projectRoles()->attach(
            Role::factory()->granting(Ability::ViewTestCases)->create(),
            ['test_project_id' => $project->id],
        );

        $member->delete();

        $this->assertDatabaseCount('test_project_user', 0);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $project = TestProject::factory()->create();

        $this->get(route('projects.members.index', $project))
            ->assertRedirect(route('login'));
    }
}
