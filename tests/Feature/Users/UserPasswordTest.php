<?php

namespace Tests\Feature\Users;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_sets_a_forgotten_password()
    {
        $subject = User::factory()->create();

        $this->actingAs($this->roleAssigner())
            ->put(route('users.password.update', $subject), [
                'password' => 'correct-horse-battery-staple',
                'password_confirmation' => 'correct-horse-battery-staple',
            ])
            ->assertRedirect(route('users.edit', $subject));

        $this->assertTrue(Hash::check('correct-horse-battery-staple', $subject->refresh()->password));
    }

    /**
     * Taking over an account is at least as powerful as changing what it may
     * do, so it takes the same ability. Were `manage_users` enough, its holder
     * could take over a super admin and inherit everything — which would
     * quietly make it an administrator-level ability.
     */
    public function test_managing_users_alone_does_not_permit_setting_a_password()
    {
        $subject = User::factory()->create();
        $original = $subject->password;

        $this->actingAs($this->userManager())
            ->put(route('users.password.update', $subject), [
                'password' => 'correct-horse-battery-staple',
                'password_confirmation' => 'correct-horse-battery-staple',
            ])
            ->assertForbidden();

        $this->assertSame($original, $subject->refresh()->password);
    }

    public function test_setting_a_password_requires_confirmation()
    {
        $subject = User::factory()->create();

        $this->actingAs($this->roleAssigner())
            ->put(route('users.password.update', $subject), [
                'password' => 'correct-horse-battery-staple',
                'password_confirmation' => 'something-else-entirely',
            ])
            ->assertSessionHasErrors('password');
    }

    /**
     * A reset is normally done because the account is out of its owner's
     * control, which a new password alone does nothing about — an attacker
     * with a live session would keep it until it expired.
     */
    public function test_setting_a_password_ends_the_accounts_sessions()
    {
        /** The suite runs on the `array` driver; production uses `database`. */
        config(['session.driver' => 'database']);

        $subject = User::factory()->create();
        $other = User::factory()->create();

        DB::table('sessions')->insert([
            ['id' => 'subject-session', 'user_id' => $subject->id, 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'other-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($this->roleAssigner())
            ->put(route('users.password.update', $subject), [
                'password' => 'correct-horse-battery-staple',
                'password_confirmation' => 'correct-horse-battery-staple',
            ]);

        $this->assertDatabaseMissing('sessions', ['id' => 'subject-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    }

    /**
     * The stored sessions are only authoritative on the database driver, so on
     * any other one the reset must still succeed rather than failing on a
     * table it has no business reading.
     */
    public function test_setting_a_password_succeeds_on_a_non_database_session_driver()
    {
        config(['session.driver' => 'file']);

        $subject = User::factory()->create();

        $this->actingAs($this->roleAssigner())
            ->put(route('users.password.update', $subject), [
                'password' => 'correct-horse-battery-staple',
                'password_confirmation' => 'correct-horse-battery-staple',
            ])
            ->assertRedirect(route('users.edit', $subject));

        $this->assertTrue(Hash::check('correct-horse-battery-staple', $subject->refresh()->password));
    }

    /**
     * Otherwise a device that ticked "remember me" carries on under the old
     * password.
     */
    public function test_setting_a_password_cycles_the_remember_token()
    {
        $subject = User::factory()->create(['remember_token' => 'original-token']);

        $this->actingAs($this->roleAssigner())
            ->put(route('users.password.update', $subject), [
                'password' => 'correct-horse-battery-staple',
                'password_confirmation' => 'correct-horse-battery-staple',
            ]);

        $this->assertNotSame('original-token', $subject->refresh()->remember_token);
    }

    public function test_an_administrator_emails_a_reset_link(): void
    {
        Notification::fake();

        $subject = User::factory()->create();

        $this->actingAs($this->roleAssigner())
            ->post(route('users.password-reset.store', $subject))
            ->assertRedirect(route('users.edit', $subject));

        Notification::assertSentTo($subject, ResetPassword::class);
        $this->assertSame(
            AuditAction::UserPasswordResetLinkSent->value,
            AuditEvent::query()->latest('id')->value('action'),
        );
    }

    public function test_managing_users_alone_does_not_permit_emailing_a_reset_link(): void
    {
        Notification::fake();

        $subject = User::factory()->create();

        $this->actingAs($this->userManager())
            ->post(route('users.password-reset.store', $subject))
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_emailing_a_reset_link_ends_the_accounts_sessions(): void
    {
        Notification::fake();
        config(['session.driver' => 'database']);

        $subject = User::factory()->create(['remember_token' => 'original-token']);

        DB::table('sessions')->insert([
            ['id' => 'subject-session', 'user_id' => $subject->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($this->roleAssigner())
            ->post(route('users.password-reset.store', $subject));

        $this->assertDatabaseMissing('sessions', ['id' => 'subject-session']);
        $this->assertNotSame('original-token', $subject->refresh()->remember_token);
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
