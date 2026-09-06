<?php

namespace App\Http\Controllers\RoleAssignments;

use App\Actions\Authorization\RoleResolver;
use App\Actions\RoleAssignments\AssignRoleInScope;
use App\Actions\RoleAssignments\RevokeRoleInScope;
use App\Concerns\PresentsScopeMembers;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleAssignments\RoleAssignmentRequest;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who holds a role for one project, and changing it.
 *
 * Reachable by whoever can assign roles in the project, or by any project
 * administrator — see RoleResolver::mayAssignRolesIn() for why the second
 * clause has to exist.
 */
class ProjectMemberController extends Controller
{
    use PresentsScopeMembers;

    public function index(
        Request $request,
        TestProject $testProject,
        RoleResolver $roleResolver,
    ): Response {
        $actor = $this->actingUser($request);

        abort_unless($roleResolver->mayAssignRolesIn($actor, $testProject), 403);

        return Inertia::render('test-projects/members', [
            'project' => ['id' => $testProject->id, 'name' => $testProject->name],
            'isRestricted' => ! $testProject->is_public,
            ...$this->memberProps($testProject, $actor),
        ]);
    }

    public function store(
        RoleAssignmentRequest $request,
        TestProject $testProject,
        AssignRoleInScope $assignRoleInScope,
    ): RedirectResponse {
        $assignRoleInScope(
            $this->actingUser($request),
            $testProject,
            $request->member(),
            $request->role(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project role assigned.')]);

        return to_route('projects.members.index', $testProject);
    }

    public function destroy(
        Request $request,
        TestProject $testProject,
        User $user,
        RevokeRoleInScope $revokeRoleInScope,
    ): RedirectResponse {
        $revokeRoleInScope($this->actingUser($request), $testProject, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project role removed.')]);

        return to_route('projects.members.index', $testProject);
    }
}
