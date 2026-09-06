<?php

namespace Tests\Feature\Auth;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('projects.index', absolute: false));
    }

    public function test_new_users_receive_the_default_role()
    {
        $role = Role::factory()->asDefault()->create();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertSame($role->id, User::firstWhere('email', 'test@example.com')->role_id);
    }

    public function test_registration_is_unavailable_when_self_signup_is_off(): void
    {
        config(['auth.self_signup' => false]);

        $this->get(route('register'))->assertNotFound();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_a_user_manager_is_notified_of_a_public_registration(): void
    {
        Notification::fake();

        $manager = User::factory()
            ->for(Role::factory()->granting(Ability::ManageUsers), 'role')
            ->create();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Notification::assertSentTo($manager, UserRegisteredNotification::class);
        Notification::assertNotSentTo(
            User::query()->where('email', 'test@example.com')->sole(),
            UserRegisteredNotification::class,
        );
    }
}
