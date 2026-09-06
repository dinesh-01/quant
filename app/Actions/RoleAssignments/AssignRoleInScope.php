<?php

namespace App\Actions\RoleAssignments;

use App\Actions\Audit\AuditLogger;
use App\Actions\Authorization\RoleResolver;
use App\Concerns\AuthorizesRoleAssignment;
use App\Enums\AuditAction;
use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Gives a user a role for one project or one plan, replacing any role they
 * already held there.
 *
 * One action covers both scopes because the rule is identical and only the
 * pivot table differs, which the models' `members()` relations already hide.
 */
final class AssignRoleInScope
{
    use AuthorizesRoleAssignment;

    public function __construct(
        private readonly RoleResolver $roleResolver,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(
        User $actor,
        TestProject|TestPlan $scope,
        User $member,
        Role $role,
    ): void {
        $this->authorizeRoleAssignment($this->roleResolver, $actor, $scope, $member);

        /**
         * `is_super_admin` is only honoured on a user's global role, so
         * offering it here would promise powers the resolver ignores.
         */
        if ($role->is_super_admin) {
            throw ValidationException::withMessages([
                'role_id' => 'A super admin role can only be given as a global role, not for a single project or plan.',
            ]);
        }

        /**
         * Updates the existing pivot row rather than adding a second, which the
         * pivot's composite primary key would reject anyway.
         */
        $scope->members()->syncWithoutDetaching([
            $member->getKey() => ['role_id' => $role->getKey()],
        ]);

        /**
         * The scope is the subject, not the member, so that a project's log
         * reads as the history of who was given access to it. The member is in
         * `properties`, which is where a log filtered by person would look.
         */
        $this->audit->record(AuditAction::ScopeRoleAssigned, $actor, $scope, [
            'member_id' => $member->getKey(),
            'member_email' => $member->email,
            'role_id' => $role->getKey(),
            'role_name' => $role->name,
        ]);
    }
}
