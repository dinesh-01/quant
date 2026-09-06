<?php

namespace App\Actions\Users;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Adds a user account.
 *
 * `manage_users` and `assign_global_roles` are separate system abilities, and
 * this keeps them separate: creating the account needs the first, and giving it
 * a global role needs the second. So an administrator who can add people
 * cannot also promote them unless that was granted explicitly.
 *
 * The account is created with its email already marked verified, because the
 * administrator typing the address is the assertion that it is real. A random
 * password is stored and a reset link is emailed — they set their own
 * password. Until SMTP is configured the link lands in the log mailer.
 */
final class CreateUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, role_id: int|null, is_active: bool, expires_at: string|null}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, array $attributes): User
    {
        Gate::forUser($actor)->authorize(Ability::ManageUsers->value);

        if ($attributes['role_id'] !== null) {
            Gate::forUser($actor)->authorize(Ability::AssignGlobalRoles->value);
        }

        /**
         * `forceFill` rather than `fill`: `role_id`, `is_active` and
         * `expires_at` are deliberately outside `$fillable` so that no
         * user-facing form can reach them, and this action has just checked it
         * is allowed to. The casts still apply, so the password is hashed and
         * the date string is parsed.
         */
        $user = (new User)->forceFill([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Str::password(32),
            'role_id' => $attributes['role_id'],
            'is_active' => $attributes['is_active'],
            'expires_at' => $attributes['expires_at'],
            'email_verified_at' => now(),
        ]);

        /** Read while the model is still dirty, so the trail keeps the starting values. */
        $properties = $this->audit->changes($user);

        $user->save();

        $this->audit->record(AuditAction::UserCreated, $actor, $user, $properties);

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }
}
