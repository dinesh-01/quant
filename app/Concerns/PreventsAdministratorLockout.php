<?php

namespace App\Concerns;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the application administrable.
 *
 * Two ways to lose access permanently, both reachable from the user screens
 * and neither guarded in legacy:
 *
 * 1. Turning off your own account. `EnsureUserIsActive` ends the session on the
 *    very next request, so the mistake takes effect before you can undo it.
 * 2. Removing the last account that can assign global roles. `assign_global_roles`
 *    is only readable from a *global* role, and a global role can only be
 *    granted by someone holding that ability, so once nobody holds it no one
 *    can ever grant it again. Recovery means editing the database by hand.
 *
 * The second is the real invariant and covers other people as well as yourself;
 * the first is kept separately because it is recoverable in principle (another
 * administrator can switch you back on) but never intentional.
 *
 * The second invariant is reachable from two directions — one account at a
 * time, or a role edit that reaches every holder of that role at once — so
 * there is a guard for each.
 */
trait PreventsAdministratorLockout
{
    /**
     * Ids of the roles that can grant a global role, super admins included.
     *
     * @var list<int>|null
     */
    private ?array $roleAssignerRoleIds = null;

    /**
     * Refuse a change that would immediately lock the actor out of the
     * application.
     *
     * Covers deactivation and a lapsed expiry date in one check, because
     * `isActive()` is what the middleware and the resolver both read.
     *
     * @throws ValidationException
     */
    protected function guardAgainstSelfLockout(User $actor, User $subject): void
    {
        if (! $actor->is($subject) || $subject->isActive()) {
            return;
        }

        throw ValidationException::withMessages([
            'is_active' => __('You cannot deactivate or expire your own account. Ask another administrator to do it for you.'),
        ]);
    }

    /**
     * Refuse a change that would leave nobody able to assign a global role.
     *
     * Call it only when the subject is losing that standing, so an installation
     * that is already in this state does not have every unrelated edit refused
     * on its way out of it.
     *
     * @throws ValidationException
     */
    protected function guardAgainstRemovingTheLastRoleAssigner(User $subject): void
    {
        $remains = User::query()
            ->whereKeyNot($subject->getKey())
            ->whereIn('role_id', $this->roleAssignerRoleIds())
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', today()),
            )
            ->exists();

        if ($remains) {
            return;
        }

        throw ValidationException::withMessages([
            'role_id' => __('This is the only usable account that can assign global roles. Give another account such a role first, or nobody will be able to administer this application.'),
        ]);
    }

    /**
     * Refuse a role edit that would leave nobody able to assign a global role.
     *
     * The same invariant as above approached from the other side. Editing a
     * role reaches every holder at once, so stripping `assign_global_roles`
     * from the last role that grants it — or clearing `is_super_admin` from the
     * last role that has it — locks out more people than editing one account
     * ever could.
     *
     * Takes the role as it stands in memory, before saving, so the check is on
     * the proposed grants rather than the stored ones — and compares it against
     * the stored ones to establish that the change is what removes the standing.
     * Without that comparison, an installation that already has no usable
     * assigner would have every unrelated role edit refused, blaming a role
     * that never granted anything of the sort, and leaving no way to edit out
     * of the state from the interface.
     *
     * @throws ValidationException
     */
    protected function guardRoleChangeLeavesSomeoneAdministrative(Role $role): void
    {
        if ($this->grantsAdministration($role) || ! $this->grantsAdministration($this->asStored($role))) {
            return;
        }

        $stillGranting = Role::query()
            ->get()
            ->reject(fn (Role $stored): bool => $stored->is($role))
            ->filter(fn (Role $stored): bool => $this->grantsAdministration($stored))
            ->modelKeys();

        $remains = $stillGranting !== [] && User::query()
            ->whereIn('role_id', $stillGranting)
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', today()),
            )
            ->exists();

        if ($remains) {
            return;
        }

        throw ValidationException::withMessages([
            'abilities' => __('This is the only role that lets anyone assign global roles. Taking that away would leave nobody able to administer this application.'),
        ]);
    }

    /**
     * Whether a role lets its holders grant a global role.
     *
     * A super admin grants everything without listing anything, so the flag
     * has to be tested alongside the ability.
     */
    private function grantsAdministration(Role $role): bool
    {
        return $role->is_super_admin || $role->grants(Ability::AssignGlobalRoles);
    }

    /**
     * The role as the database still has it, for comparing against a pending
     * change. Built from the original attributes so the casts still apply and
     * `grantsAdministration()` can read it exactly as it reads a live model.
     */
    private function asStored(Role $role): Role
    {
        return (new Role)->setRawAttributes($role->getRawOriginal(), sync: true);
    }

    /**
     * Whether the user, as they stand in memory, can assign global roles.
     *
     * Compare before and after filling a change to learn whether the change
     * takes that standing away.
     */
    protected function isRoleAssigner(User $user): bool
    {
        return $user->isActive()
            && $user->role_id !== null
            && in_array($user->role_id, $this->roleAssignerRoleIds(), true);
    }

    /**
     * Resolved in PHP rather than SQL: `abilities` is a JSON enum collection,
     * and a super-admin role grants everything without listing anything, so
     * neither test is expressible as a column predicate.
     *
     * @return list<int>
     */
    private function roleAssignerRoleIds(): array
    {
        return $this->roleAssignerRoleIds ??= array_values(
            Role::query()
                ->get()
                ->filter(fn (Role $role): bool => $role->is_super_admin
                    || $role->grants(Ability::AssignGlobalRoles),
                )
                ->modelKeys(),
        );
    }
}
