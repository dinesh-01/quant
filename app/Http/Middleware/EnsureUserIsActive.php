<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a deactivated or expired account out of the application.
 *
 * Until this existed, `is_active` and `expires_at` were honoured only by
 * `RoleResolver`, so a deactivated user could still sign in and hold a session.
 * They failed every ability check, which limited the damage, but they were
 * inside the application and their session stayed valid indefinitely.
 *
 * This runs on the whole `web` group rather than at login, because login is not
 * the only way in. Passkeys authenticate through WebAuthn, the two-factor
 * challenge completes in its own controller, and a "remember me" cookie
 * re-authenticates with no login request at all — a check in the password
 * pipeline would miss all three. More importantly, none of them cover the case
 * that actually matters: an account deactivated *while* its session is live.
 * Checking every request is the only version of this with no gaps.
 *
 * The trade-off is that signing in with correct credentials on a deactivated
 * account briefly creates a session, which the next request destroys before the
 * user can do anything with it. Rejecting inside the login pipeline instead
 * would mean re-implementing credential verification, and would give up
 * Laravel's automatic password rehashing on login.
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isActive()) {
            return $next($request);
        }

        $message = $user->is_active
            ? __('Your access to this application expired on :date. Ask an administrator to extend it.', [
                'date' => $user->expires_at?->toFormattedDateString() ?? '',
            ])
            : __('This account has been deactivated. Ask an administrator to restore it.');

        if ($request->is('api/*') || ! $request->hasSession()) {
            return new JsonResponse(['message' => $message], 401);
        }

        /**
         * `logout()` cycles the remember token, so the cookie cannot let them
         * straight back in. Invalidating and regenerating happens before the
         * redirect is built so the message is flashed to the fresh session
         * rather than the one being thrown away.
         */
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], 401);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
