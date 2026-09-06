<?php

namespace App\Actions\Roles;

use App\Actions\Audit\AuditLogger;
use App\Concerns\ManagesTheDefaultRole;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a role.
 *
 * A new role grants nothing until abilities are ticked, which is the safe
 * default: a role created by accident cannot widen anyone's access.
 */
final class CreateRole
{
    use ManagesTheDefaultRole;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, description: string|null, abilities: list<Ability>, is_super_admin: bool, is_default: bool}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, array $attributes): Role
    {
        Gate::forUser($user)->authorize(Ability::ManageRoles->value);

        return DB::transaction(function () use ($user, $attributes): Role {
            $role = Role::query()->create($attributes);

            $this->keepOneDefaultRole($role);

            $this->audit->record(AuditAction::RoleCreated, $user, $role, [
                'name' => $role->name,
                'abilities' => $role->abilities->map(fn (Ability $ability): string => $ability->value)->all(),
                'is_super_admin' => $role->is_super_admin,
                'is_default' => $role->is_default,
            ]);

            return $role;
        });
    }
}
