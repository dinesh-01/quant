<?php

namespace App\Actions\RoleAssignments;

use App\Actions\Audit\AuditLogger;
use App\Actions\Authorization\RoleResolver;
use App\Concerns\AuthorizesRoleAssignment;
use App\Enums\AuditAction;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Removes a user's role for one project or one plan.
 *
 * On a public scope this returns them to whatever their global role gives. On a
 * restricted one it removes their access entirely, which is why
 * AuthorizesRoleAssignment refuses to let a non-administrator do this to
 * themselves.
 */
final class RevokeRoleInScope
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
    public function __invoke(User $actor, TestProject|TestPlan $scope, User $member): void
    {
        $this->authorizeRoleAssignment($this->roleResolver, $actor, $scope, $member);

        $scope->members()->detach($member->getKey());

        $this->audit->record(AuditAction::ScopeRoleRevoked, $actor, $scope, [
            'member_id' => $member->getKey(),
            'member_email' => $member->email,
        ]);
    }
}
