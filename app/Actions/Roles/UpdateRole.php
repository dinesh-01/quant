<?php

namespace App\Actions\Roles;

use App\Actions\Audit\AuditLogger;
use App\Concerns\ManagesTheDefaultRole;
use App\Concerns\PreventsAdministratorLockout;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a role: its name, description, granted abilities and two flags.
 *
 * This is the widest-reaching write in the application. A role edit changes
 * what every holder of that role can do, in every project, at once — which is
 * why both invariants are checked here before anything is saved.
 *
 * Note that `manage_roles` is therefore an administrator-level ability in its
 * own right: whoever holds it can add any ability to any role, including one
 * they hold themselves. It is not a narrow permission and should not be
 * granted as if it were.
 */
final class UpdateRole
{
    use ManagesTheDefaultRole, PreventsAdministratorLockout;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, description: string|null, abilities: list<Ability>, is_super_admin: bool, is_default: bool}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the change would leave nobody able to administer, or no default role
     */
    public function __invoke(User $user, Role $role, array $attributes): Role
    {
        Gate::forUser($user)->authorize(Ability::ManageRoles->value);

        $wasDefault = $role->is_default;

        $role->fill($attributes);

        $this->guardRoleChangeLeavesSomeoneAdministrative($role);

        if ($wasDefault && ! $role->is_default) {
            $this->guardAgainstRemovingTheLastDefaultRole($role);
        }

        /**
         * This is the change most worth being able to reconstruct later: the
         * before and after ability lists say exactly what every holder gained
         * or lost, which no amount of looking at the role now will tell you.
         */
        $properties = $this->audit->changes($role);

        return DB::transaction(function () use ($user, $role, $properties): Role {
            $role->save();

            $this->keepOneDefaultRole($role);

            if ($properties !== []) {
                $this->audit->record(AuditAction::RoleUpdated, $user, $role, $properties);
            }

            return $role;
        });
    }
}
