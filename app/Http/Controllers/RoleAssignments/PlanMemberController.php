<?php

namespace App\Http\Controllers\RoleAssignments;

use App\Actions\Authorization\RoleResolver;
use App\Actions\RoleAssignments\AssignRoleInScope;
use App\Actions\RoleAssignments\RevokeRoleInScope;
use App\Concerns\PresentsScopeMembers;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleAssignments\RoleAssignmentRequest;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who holds a role for one plan, and changing it.
 *
 * A plan role overrides the project role for that plan alone, in both
 * directions: it can grant more than the project role or less.
 */
class PlanMemberController extends Controller
{
    use PresentsScopeMembers;

    public function index(
        Request $request,
        TestPlan $testPlan,
        RoleResolver $roleResolver,
    ): Response {
        $actor = $this->actingUser($request);

        abort_unless($roleResolver->mayAssignRolesIn($actor, $testPlan), 403);

        return Inertia::render('test-plans/members', [
            'project' => [
                'id' => $testPlan->testProject->id,
                'name' => $testPlan->testProject->name,
            ],
            'plan' => ['id' => $testPlan->id, 'name' => $testPlan->name],
            'isRestricted' => ! $testPlan->is_public,
            ...$this->memberProps($testPlan, $actor),
        ]);
    }

    public function store(
        RoleAssignmentRequest $request,
        TestPlan $testPlan,
        AssignRoleInScope $assignRoleInScope,
    ): RedirectResponse {
        $assignRoleInScope(
            $this->actingUser($request),
            $testPlan,
            $request->member(),
            $request->role(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan role assigned.')]);

        return to_route('plans.members.index', $testPlan);
    }

    public function destroy(
        Request $request,
        TestPlan $testPlan,
        User $user,
        RevokeRoleInScope $revokeRoleInScope,
    ): RedirectResponse {
        $revokeRoleInScope($this->actingUser($request), $testPlan, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan role removed.')]);

        return to_route('plans.members.index', $testPlan);
    }
}
