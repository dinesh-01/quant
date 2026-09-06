<?php

namespace App\Actions\Users;

use App\Actions\Audit\AuditLogger;
use App\Concerns\PreventsAdministratorLockout;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a user account: name, email, active flag, expiry date and global role.
 *
 * Deactivating and setting an expiry date live here rather than in actions of
 * their own, because both are just this form's fields and both are reversible.
 * There is deliberately no delete: later phases attribute test case versions
 * and executions to a user, and deleting one would either take that history
 * with it or leave it unattributed. Deactivating keeps the record and stops the
 * access, which is what deleting a user is nearly always meant to achieve.
 * Legacy did allow deletion.
 *
 * `assign_global_roles` is checked only when the role actually changes, so one
 * form can serve an administrator who may edit people but not promote them.
 */
final class UpdateUser
{
    use PreventsAdministratorLockout;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, role_id: int|null, is_active: bool, expires_at: string|null}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the change would lock the actor out or leave nobody able to administer
     */
    public function __invoke(User $actor, User $user, array $attributes): User
    {
        Gate::forUser($actor)->authorize(Ability::ManageUsers->value);

        if ($attributes['role_id'] !== $user->role_id) {
            Gate::forUser($actor)->authorize(Ability::AssignGlobalRoles->value);
        }

        $wasRoleAssigner = $this->isRoleAssigner($user);

        /**
         * `forceFill` because `role_id`, `is_active` and `expires_at` are
         * deliberately not mass assignable — the profile form shares this model
         * and must never reach them. The guards below read the filled model, so
         * they judge the change rather than the current state.
         */
        $user->forceFill($attributes);

        $this->guardAgainstSelfLockout($actor, $user);

        if ($wasRoleAssigner && ! $this->isRoleAssigner($user)) {
            $this->guardAgainstRemovingTheLastRoleAssigner($user);
        }

        $properties = $this->audit->changes($user);

        $user->save();

        /**
         * An edit that changed nothing is not worth a row. Recording it would
         * fill the log with entries a reader has to open to discover are empty.
         */
        if ($properties !== []) {
            $this->audit->record(AuditAction::UserUpdated, $actor, $user, $properties);
        }

        return $user;
    }
}
