<?php

namespace App\Http\Controllers\Roles;

use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\UpdateRole;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\RoleDeleteRequest;
use App\Http\Requests\Roles\RoleStoreRequest;
use App\Http\Requests\Roles\RoleUpdateRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Role administration: what each role grants, and who is relying on it.
 *
 * The usage counts are part of the screen rather than a detail, because a role
 * edit reaches every holder at once and the person making it needs to see how
 * far it reaches before they save.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Ability::ManageRoles->value);

        $roles = Role::query()
            ->withCount(['users', 'projectMembers', 'planMembers'])
            ->orderBy('name')
            ->get();

        return Inertia::render('roles/index', [
            'roles' => array_values($roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_super_admin' => $role->is_super_admin,
                'is_default' => $role->is_default,
                'abilities_count' => $role->abilities->count(),
                'users_count' => (int) $role->getAttribute('users_count'),
                'assignments_count' => (int) $role->getAttribute('project_members_count')
                    + (int) $role->getAttribute('plan_members_count'),
            ])->all()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize(Ability::ManageRoles->value);

        return Inertia::render('roles/create', [
            'abilityGroups' => $this->abilityGroups(),
        ]);
    }

    public function store(RoleStoreRequest $request, CreateRole $createRole): RedirectResponse
    {
        $createRole($this->actingUser($request), $request->newRoleAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    public function edit(Request $request, Role $role): Response
    {
        Gate::authorize(Ability::ManageRoles->value);

        $role->loadCount(['users', 'projectMembers', 'planMembers']);

        return Inertia::render('roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'abilities' => array_values($role->abilities
                    ->map(fn (Ability $ability): string => $ability->value)
                    ->all()),
                'is_super_admin' => $role->is_super_admin,
                'is_default' => $role->is_default,
                'users_count' => (int) $role->getAttribute('users_count'),
                'assignments_count' => (int) $role->getAttribute('project_members_count')
                    + (int) $role->getAttribute('plan_members_count'),
                'is_own_role' => $this->actingUser($request)->role_id === $role->id,
            ],
            'abilityGroups' => $this->abilityGroups(),
        ]);
    }

    public function update(
        RoleUpdateRequest $request,
        Role $role,
        UpdateRole $updateRole,
    ): RedirectResponse {
        $updateRole($this->actingUser($request), $role, $request->editedRoleAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index');
    }

    public function destroy(
        RoleDeleteRequest $request,
        Role $role,
        DeleteRole $deleteRole,
    ): RedirectResponse {
        $deleteRole($this->actingUser($request), $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }

    /**
     * The ability catalogue, grouped for a form that would otherwise be
     * fifty-four undifferentiated checkboxes.
     *
     * `is_system` travels with each one so the form can say which grants are
     * ignored unless the role is someone's global role — the single most
     * confusing thing about scoped roles.
     *
     * @return list<array{name: string, abilities: list<array{value: string, label: string, is_system: bool}>}>
     */
    private function abilityGroups(): array
    {
        $groups = [];

        foreach (Ability::grouped() as $name => $abilities) {
            $groups[] = [
                'name' => $name,
                'abilities' => array_map(fn (Ability $ability): array => [
                    'value' => $ability->value,
                    'label' => $ability->label(),
                    'is_system' => $ability->isSystem(),
                ], $abilities),
            ];
        }

        return $groups;
    }
}
