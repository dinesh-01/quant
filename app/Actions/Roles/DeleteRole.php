<?php

namespace App\Actions\Roles;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Removes a role, but only one nobody is using.
 *
 * The database would let this through silently and destructively:
 * `users.role_id` is `nullOnDelete`, so every holder would quietly drop to no
 * global role and be denied every ability, and the project and plan pivots
 * cascade, so scoped assignments would simply vanish. Neither leaves a trace
 * of what the role used to grant.
 *
 * Refusing while the role is in use is what makes deletion safe, and it
 * subsumes the protections the migration plan asked for: the last super-admin
 * role cannot be deleted while anyone holds it, and a role held by nobody is
 * harmless to remove. Reassign its holders first — the user screens and the
 * project and plan member screens are where that is done.
 *
 * The default role is protected separately, because it is load-bearing for
 * registration even before anyone holds it.
 */
final class DeleteRole
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException when the role is still in use, or is the default
     */
    public function __invoke(User $user, Role $role): void
    {
        Gate::forUser($user)->authorize(Ability::ManageRoles->value);

        if ($role->is_default) {
            throw ValidationException::withMessages([
                'confirm_name' => __('This is the role new accounts start with, so it cannot be deleted. Make another role the default first.'),
            ]);
        }

        $holders = $this->countHolders($role);

        if ($holders !== []) {
            throw ValidationException::withMessages([
                'confirm_name' => __('This role is still in use by :usages. Move them to another role first — deleting it would take their access away without recording what it granted.', [
                    'usages' => $this->describe($holders),
                ]),
            ]);
        }

        DB::transaction(function () use ($user, $role): void {
            /**
             * Recorded before the row goes, and with the name and abilities
             * copied into `properties`: the subject reference will not resolve
             * afterwards, so anything the log needs to show has to be captured
             * here rather than looked up later.
             */
            $this->audit->record(AuditAction::RoleDeleted, $user, $role, [
                'name' => $role->name,
                'abilities' => $role->abilities->map(fn (Ability $ability): string => $ability->value)->all(),
                'is_super_admin' => $role->is_super_admin,
            ]);

            $role->delete();
        });
    }

    /**
     * Where the role is still granted, keyed by noun, omitting the zeroes.
     *
     * @return array<string, int>
     */
    private function countHolders(Role $role): array
    {
        $counts = [
            'account' => $role->users()->count(),
            'project assignment' => $role->projectMembers()->count(),
            'plan assignment' => $role->planMembers()->count(),
        ];

        return array_filter($counts, fn (int $count): bool => $count > 0);
    }

    /**
     * @param  array<string, int>  $holders
     */
    private function describe(array $holders): string
    {
        $parts = [];

        foreach ($holders as $noun => $count) {
            $parts[] = $count.' '.($count === 1 ? $noun : $noun.'s');
        }

        return implode(', ', $parts);
    }
}
