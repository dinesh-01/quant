<?php

namespace App\Concerns;

use App\Models\Role;
use Illuminate\Validation\ValidationException;

/**
 * Keeps exactly one role flagged `is_default`.
 *
 * Registration reads that flag to decide what a new account starts with
 * (`CreateNewUser`), taking the *first* match. Two defaults would make that an
 * arbitrary choice between them, and none at all would create accounts with no
 * global role, which are denied every ability — a signup that appears to work
 * and produces a user who can see nothing.
 */
trait ManagesTheDefaultRole
{
    /**
     * Make this role the only default, if it claims to be one.
     *
     * Runs inside the caller's transaction so the flag is never briefly held
     * by two roles at once.
     */
    protected function keepOneDefaultRole(Role $role): void
    {
        if (! $role->is_default) {
            return;
        }

        Role::query()
            ->whereKeyNot($role->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * Refuse to leave no default role at all.
     *
     * Call only when the role is losing the flag, so an installation that
     * somehow has no default does not have every unrelated role edit refused.
     *
     * @throws ValidationException
     */
    protected function guardAgainstRemovingTheLastDefaultRole(Role $role): void
    {
        if ($role->is_default) {
            return;
        }

        $anotherExists = Role::query()
            ->whereKeyNot($role->getKey())
            ->where('is_default', true)
            ->exists();

        if ($anotherExists) {
            return;
        }

        throw ValidationException::withMessages([
            'is_default' => __('Some role has to be the one new accounts start with. Mark another role as the default first.'),
        ]);
    }
}
