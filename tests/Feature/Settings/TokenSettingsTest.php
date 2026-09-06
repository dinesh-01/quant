<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_tokens_page(): void
    {
        $this->get(route('tokens.index'))
            ->assertRedirect(route('login'));
    }

    public function test_the_tokens_page_requires_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tokens.index'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_creates_a_token_and_shows_the_plaintext_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('tokens.index'))
            ->post(route('tokens.store'), ['name' => 'CI pipeline'])
            ->assertRedirect(route('tokens.index'))
            ->assertSessionHas('plainTextToken');

        $plainTextToken = session('plainTextToken');
        $this->assertIsString($plainTextToken);
        $this->assertNotSame('', $plainTextToken);

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('CI pipeline', $user->tokens()->sole()->name);

        $this->actingAs($user)
            ->get(route('tokens.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/tokens')
                ->has('tokens', 1)
                ->where('tokens.0.name', 'CI pipeline')
                ->where('plainTextToken', $plainTextToken));

        $this->actingAs($user)
            ->get(route('tokens.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('plainTextToken', null));
    }

    public function test_revokes_a_token_that_belongs_to_the_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('old')->accessToken;

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('tokens.destroy', $token))
            ->assertRedirect();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_returns_404_when_revoking_another_users_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $other->createToken('theirs')->accessToken;

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('tokens.destroy', $token))
            ->assertNotFound();

        $this->assertTrue(PersonalAccessToken::query()->whereKey($token->id)->exists());
    }
}
