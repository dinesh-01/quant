<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use AuthenticatesApiTokens;
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }

    public function test_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->apiAs($user)
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    public function test_returns_401_when_the_account_is_deactivated(): void
    {
        $user = User::factory()->inactive()->create();

        $this->apiAs($user)
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'This account has been deactivated. Ask an administrator to restore it.');
    }
}
