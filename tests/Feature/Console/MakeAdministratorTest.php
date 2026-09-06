<?php

namespace Tests\Feature\Console;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The only way into a fresh installation, and the documented way back out of a
 * lockout.
 */
class MakeAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_administrator_who_can_reach_every_screen()
    {
        Role::factory()->superAdmin()->create();

        $this->artisan('app:make-administrator', [
            '--name' => 'Ada Lovelace',
            '--email' => 'ada@example.com',
            '--password' => 'correct-horse-battery-staple',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'ada@example.com')->sole();

        $this->assertTrue(Hash::check('correct-horse-battery-staple', $user->password));
        $this->assertTrue($user->can(Ability::ManageUsers->value));
        $this->assertTrue($user->can(Ability::ManageRoles->value));
        $this->assertTrue($user->isActive());
    }

    /**
     * Otherwise the new account would sit on the verification prompt, which
     * needs outbound mail that a fresh installation does not have.
     */
    public function test_the_created_administrator_can_use_the_application_immediately()
    {
        Role::factory()->superAdmin()->create();

        $this->artisan('app:make-administrator', [
            '--name' => 'Ada Lovelace',
            '--email' => 'ada@example.com',
            '--password' => 'correct-horse-battery-staple',
        ]);

        $this->actingAs(User::query()->where('email', 'ada@example.com')->sole())
            ->get(route('users.index'))
            ->assertOk();
    }

    /**
     * With no roles seeded there is nothing to grant, so the command has to be
     * able to bootstrap one.
     */
    public function test_it_creates_an_unrestricted_role_when_none_exists()
    {
        $this->artisan('app:make-administrator', [
            '--name' => 'Ada Lovelace',
            '--email' => 'ada@example.com',
            '--password' => 'correct-horse-battery-staple',
        ])->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'ada@example.com')->sole()->can(Ability::ManageRoles->value));
        $this->assertSame(1, Role::query()->where('is_super_admin', true)->count());
    }

    public function test_it_reuses_an_existing_unrestricted_role()
    {
        $role = Role::factory()->superAdmin()->create();

        $this->artisan('app:make-administrator', [
            '--name' => 'Ada Lovelace',
            '--email' => 'ada@example.com',
            '--password' => 'correct-horse-battery-staple',
        ]);

        $this->assertSame($role->id, User::query()->where('email', 'ada@example.com')->sole()->role_id);
        $this->assertSame(1, Role::query()->where('is_super_admin', true)->count());
    }

    /**
     * The recovery case: the account exists but cannot administer, so promote
     * it rather than refusing the duplicate email.
     */
    public function test_it_promotes_an_existing_account()
    {
        $role = Role::factory()->superAdmin()->create();
        $existing = User::factory()->create(['email' => 'ada@example.com']);

        $this->artisan('app:make-administrator', ['--email' => 'ada@example.com'])
            ->assertSuccessful();

        $this->assertSame($role->id, $existing->refresh()->role_id);
    }

    /**
     * An unusable administrator is no use as a recovery path, so a promotion
     * clears whatever made the account unusable.
     */
    public function test_promoting_reactivates_a_deactivated_or_expired_account()
    {
        Role::factory()->superAdmin()->create();
        $existing = User::factory()->inactive()->expired()->create(['email' => 'ada@example.com']);

        $this->artisan('app:make-administrator', ['--email' => 'ada@example.com']);

        $existing->refresh();

        $this->assertTrue($existing->isActive());
        $this->assertNull($existing->expires_at);
    }

    public function test_it_refuses_a_password_that_fails_the_policy()
    {
        Role::factory()->superAdmin()->create();

        $this->artisan('app:make-administrator', [
            '--name' => 'Ada Lovelace',
            '--email' => 'ada@example.com',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_it_refuses_an_invalid_email()
    {
        $this->artisan('app:make-administrator', [
            '--name' => 'Ada Lovelace',
            '--email' => 'not-an-email',
            '--password' => 'correct-horse-battery-staple',
        ])->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
