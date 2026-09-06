<?php

namespace App\Actions\Authorization;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Decides which role applies to a user for a given scope, and answers ability
 * checks against it.
 *
 * A user has one global role and may additionally hold a role for individual
 * test projects and test plans. The most specific role wins outright: a project
 * or plan role replaces the global role rather than adding to it, so a role can
 * both grant and withhold abilities relative to the global role.
 *
 * System abilities are the exception. They describe application-wide
 * administration that no project can delegate, so they are read from the global
 * role only.
 *
 * Restriction abilities (such as execute-only-assigned) narrow another grant.
 * A super-admin role does not receive those, or the holder would see an empty
 * execute list.
 */
final class RoleResolver
{
    /**
     * Determine whether the user may exercise an ability, optionally within a
     * test project or test plan.
     */
    public function allows(User $user, Ability $ability, TestProject|TestPlan|null $scope = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->role?->is_super_admin) {
            return ! $ability->isRestriction();
        }

        if ($ability->isSystem()) {
            return $user->role?->grants($ability) ?? false;
        }

        return $this->effectiveRole($user, $scope)?->grants($ability) ?? false;
    }

    /**
     * Whether the user may grant and revoke roles within a project or plan.
     *
     * Either their effective role for the scope grants the matching assign
     * ability, or their global role grants `manage_test_projects`.
     *
     * That second clause is not a convenience, it closes a deadlock. A
     * restricted project or plan resolves to no role at all for anyone not
     * already assigned to it, and assignment is the only way to become
     * assigned — so without this the first role on a private scope could only
     * ever be granted by a super admin, and an administrator who made a
     * project private would have locked themselves out of fixing it. Legacy
     * avoided this by making role assignment a global right; here the abilities
     * are scoped (see R3), so the escape hatch has to be explicit.
     */
    public function mayAssignRolesIn(User $user, TestProject|TestPlan $scope): bool
    {
        if ($this->allows($user, Ability::ManageTestProjects)) {
            return true;
        }

        return $this->allows(
            $user,
            $scope instanceof TestPlan ? Ability::AssignPlanRoles : Ability::AssignProjectRoles,
            $scope,
        );
    }

    /**
     * Narrow a set of projects to those in which the user may exercise the
     * ability.
     *
     * This answers exactly what `allows()` answers one project at a time, but
     * in a fixed two queries rather than a pair per project, so an index page
     * can be built without an N+1.
     *
     * Keep the two in step. If they disagree the symptom is user visible: a
     * project listed that returns 403 when opened, or one hidden that the user
     * can still reach by typing its URL. `TestProjectVisibilityTest` asserts
     * the two agree for every combination it builds.
     *
     * @param  EloquentCollection<int, TestProject>  $projects
     * @return EloquentCollection<int, TestProject>
     */
    public function projectsAllowing(
        User $user,
        Ability $ability,
        EloquentCollection $projects,
    ): EloquentCollection {
        if (! $user->isActive()) {
            return new EloquentCollection;
        }

        if ($user->role?->is_super_admin) {
            return $projects;
        }

        if ($ability->isSystem()) {
            return $user->role?->grants($ability) === true
                ? $projects
                : new EloquentCollection;
        }

        $assigned = $user->projectRoles()->get()
            ->keyBy(fn (Role $role): int => (int) $role->getAttribute('pivot')->test_project_id);

        return $projects->filter(function (TestProject $project) use ($assigned, $user, $ability): bool {
            $role = $assigned->get($project->getKey())
                ?? ($project->is_public ? $user->role : null);

            return $role?->grants($ability) ?? false;
        });
    }

    /**
     * Narrow a set of plans to those in which the user may exercise the
     * ability.
     *
     * This is the plan-shaped twin of `projectsAllowing()`, and must stay in
     * step with `effectiveRole()` for a plan: plan role first, then a public
     * plan falling through to the project role, then the global role only when
     * the project itself is public. `PlanSelectorVisibilityTest` cross-checks
     * the two.
     *
     * @param  EloquentCollection<int, TestPlan>  $plans
     * @return EloquentCollection<int, TestPlan>
     */
    public function plansAllowing(
        User $user,
        Ability $ability,
        EloquentCollection $plans,
    ): EloquentCollection {
        if (! $user->isActive()) {
            return new EloquentCollection;
        }

        if ($user->role?->is_super_admin) {
            return $plans;
        }

        if ($ability->isSystem()) {
            return $user->role?->grants($ability) === true
                ? $plans
                : new EloquentCollection;
        }

        $planRoles = $user->planRoles()->get()
            ->keyBy(fn (Role $role): int => (int) $role->getAttribute('pivot')->test_plan_id);

        $projectRoles = $user->projectRoles()->get()
            ->keyBy(fn (Role $role): int => (int) $role->getAttribute('pivot')->test_project_id);

        $plans->loadMissing('testProject');

        return $plans->filter(function (TestPlan $plan) use ($planRoles, $projectRoles, $user, $ability): bool {
            $planRole = $planRoles->get($plan->getKey());

            if ($planRole !== null) {
                return $planRole->grants($ability);
            }

            if (! $plan->is_public) {
                return false;
            }

            $project = $plan->testProject;
            $role = $projectRoles->get($project->getKey())
                ?? ($project->is_public ? $user->role : null);

            return $role?->grants($ability) ?? false;
        });
    }

    /**
     * Resolve the role that governs the user's access to the given scope.
     *
     * Returns null when the user has no applicable role, which includes a
     * private project or plan the user has not been explicitly assigned to.
     */
    public function effectiveRole(User $user, TestProject|TestPlan|null $scope = null): ?Role
    {
        if ($scope === null) {
            return $user->role;
        }

        if ($scope instanceof TestPlan) {
            $planRole = $user->planRoles()
                ->wherePivot('test_plan_id', $scope->id)
                ->first();

            if ($planRole !== null) {
                return $planRole;
            }

            if (! $scope->is_public) {
                return null;
            }
        }

        $project = $scope instanceof TestPlan ? $scope->testProject : $scope;

        $projectRole = $user->projectRoles()
            ->wherePivot('test_project_id', $project->id)
            ->first();

        if ($projectRole !== null) {
            return $projectRole;
        }

        return $project->is_public ? $user->role : null;
    }
}
