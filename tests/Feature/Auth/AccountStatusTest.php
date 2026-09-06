<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_account_is_turned_out_of_the_application()
    {
        $user = User::factory()->inactive()->create();

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_expired_account_is_turned_out_of_the_application()
    {
        $user = User::factory()->expired()->create();

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * The expiry date is inclusive, so an account set to expire today is still
     * usable for the rest of that day.
     */
    public function test_an_account_expiring_today_may_still_be_used()
    {
        $user = User::factory()->create(['expires_at' => now()]);

        $this->actingAs($user)->get(route('projects.index'))->assertOk();

        $this->assertAuthenticated();
    }

    public function test_an_active_account_is_left_alone()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('projects.index'))->assertOk();

        $this->assertAuthenticated();
    }

    /**
     * Correct credentials must not buy a deactivated account usable access,
     * whichever door it comes through.
     */
    public function test_correct_credentials_do_not_grant_a_deactivated_account_access()
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get(route('projects.index'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * Otherwise the "remember me" cookie would sign them straight back in on
     * the very next request, with no login involved.
     */
    public function test_turning_an_account_out_cycles_its_remember_token()
    {
        $user = User::factory()->inactive()->create(['remember_token' => 'original-token']);

        $this->actingAs($user)->get(route('projects.index'));

        $this->assertNotSame('original-token', $user->fresh()?->remember_token);
    }

    public function test_a_deactivated_account_making_an_expectant_request_is_refused_rather_than_redirected()
    {
        $user = User::factory()->inactive()->create();

        $response = $this->actingAs($user)->getJson(route('projects.index'));

        $response->assertUnauthorized();
        $this->assertGuest();
    }

    /**
     * An Inertia visit must get the redirect, not the 401, so the client
     * follows it to the login screen instead of surfacing an error.
     */
    public function test_a_deactivated_account_on_an_inertia_visit_is_redirected()
    {
        $user = User::factory()->inactive()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('projects.index'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_guest_reaching_the_login_screen_is_not_disturbed()
    {
        $this->get(route('login'))->assertOk();
    }
}
