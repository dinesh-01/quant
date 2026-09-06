<?php

namespace Tests\Feature\Users;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The user screens are the only place from which access can be lost with no
 * way back, so both routes to that are covered here rather than mixed in with
 * ordinary editing.
 */
class AdministratorLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_cannot_deactivate_their_own_account()
    {
        $actor = $this->roleAssigner();

        $response = $this->actingAs($actor)->put(route('users.update', $actor), [
            'name' => $actor->name,
            'email' => $actor->email,
            'role_id' => $actor->role_id,
        ]);

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue($actor->refresh()->is_active);
    }

    /**
     * The same trap through a different field: an expiry date already past
     * makes the account unusable on the next request just as deactivation does.
     */
    public function test_an_administrator_cannot_expire_their_own_account()
    {
        $actor = $this->roleAssigner();

        $response = $this->actingAs($actor)->put(route('users.update', $actor), [
            'name' => $actor->name,
            'email' => $actor->email,
            'is_active' => '1',
            'role_id' => $actor->role_id,
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('is_active');
        $this->assertNull($actor->refresh()->expires_at);
    }

    /**
     * Access lasts to the end of the expiry date, so today is still usable and
     * must not be mistaken for self-lockout.
     */
    public function test_an_administrator_may_set_their_own_expiry_to_today()
    {
        $actor = $this->roleAssigner();

        $this->actingAs($actor)->put(route('users.update', $actor), [
            'name' => $actor->name,
            'email' => $actor->email,
            'is_active' => '1',
            'role_id' => $actor->role_id,
            'expires_at' => today()->toDateString(),
        ])->assertRedirect(route('users.index'));

        $this->assertNotNull($actor->refresh()->expires_at);
    }

    public function test_the_last_account_that_can_assign_roles_cannot_be_demoted()
    {
        $actor = $this->roleAssigner();
        $plainRole = Role::factory()->create();

        $response = $this->actingAs($actor)->put(route('users.update', $actor), [
            'name' => $actor->name,
            'email' => $actor->email,
            'is_active' => '1',
            'role_id' => $plainRole->id,
        ]);

        $response->assertSessionHasErrors('role_id');
        $this->assertNotSame($plainRole->id, $actor->refresh()->role_id);
    }

    public function test_an_administrator_may_step_down_once_another_can_assign_roles()
    {
        $actor = $this->roleAssigner();
        $this->roleAssigner();
        $plainRole = Role::factory()->create();

        $this->actingAs($actor)->put(route('users.update', $actor), [
            'name' => $actor->name,
            'email' => $actor->email,
            'is_active' => '1',
            'role_id' => $plainRole->id,
        ])->assertRedirect(route('users.index'));

        $this->assertSame($plainRole->id, $actor->refresh()->role_id);
    }

    /**
     * The invariant is about the application, not about the actor: a user
     * manager must not be able to switch off the last administrator either.
     */
    public function test_the_last_account_that_can_assign_roles_cannot_be_deactivated_by_someone_else()
    {
        $actor = $this->userManager();
        $administrator = $this->roleAssigner();

        $response = $this->actingAs($actor)->put(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
        ]);

        $response->assertSessionHasErrors('role_id');
        $this->assertTrue($administrator->refresh()->is_active);
    }

    /**
     * A super-admin role grants every ability without listing any, so it has
     * to count towards the invariant even though its `abilities` column is
     * empty.
     */
    public function test_a_super_admin_counts_as_able_to_assign_roles()
    {
        $actor = $this->userManager();
        $superAdmin = User::factory()
            ->for(Role::factory()->superAdmin(), 'role')
            ->create();
        $this->roleAssigner();

        $this->actingAs($actor)->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
        ])->assertRedirect(route('users.index'));

        $this->assertFalse($superAdmin->refresh()->is_active);
    }

    /**
     * An account that cannot sign in is no use as the remaining administrator,
     * so an expired one must not satisfy the invariant.
     */
    public function test_an_expired_administrator_does_not_count_as_the_one_remaining()
    {
        $actor = $this->userManager();
        $administrator = $this->roleAssigner();

        User::factory()
            ->expired()
            ->for(Role::factory()->granting(Ability::ManageUsers, Ability::AssignGlobalRoles), 'role')
            ->create();

        $response = $this->actingAs($actor)->put(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
        ]);

        $response->assertSessionHasErrors('role_id');
        $this->assertTrue($administrator->refresh()->is_active);
    }

    /**
     * The guard applies only to changes that take the standing away, so an
     * unrelated edit to the last administrator still goes through.
     */
    public function test_editing_the_last_administrator_without_demoting_them_is_allowed()
    {
        $actor = $this->roleAssigner();

        $this->actingAs($actor)->put(route('users.update', $actor), [
            'name' => 'Renamed Administrator',
            'email' => $actor->email,
            'is_active' => '1',
            'role_id' => $actor->role_id,
        ])->assertRedirect(route('users.index'));

        $this->assertSame('Renamed Administrator', $actor->refresh()->name);
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
