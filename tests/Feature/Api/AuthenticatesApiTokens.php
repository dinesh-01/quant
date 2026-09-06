<?php

namespace Tests\Feature\Api;

use App\Models\User;

trait AuthenticatesApiTokens
{
    protected function apiAs(User $user): static
    {
        return $this->withToken($user->createToken('ci')->plainTextToken);
    }
}
