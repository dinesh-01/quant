<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The acting user on a route that requires authentication.
     *
     * Domain actions take the acting user explicitly rather than reading the
     * guard, and `Request::user()` is nullable. Every route reaching this is
     * already behind the auth middleware, so this narrows the type rather than
     * adding a real check.
     */
    protected function actingUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
