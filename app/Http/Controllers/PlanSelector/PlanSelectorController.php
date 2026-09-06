<?php

namespace App\Http\Controllers\PlanSelector;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The plans a tester may run or inspect, as opposed to the management list.
 *
 * The management page is gated on `create_test_plans` and lists every plan.
 * This page lists the plans the acting user can execute or view executions on,
 * including a private plan they hold a plan role for.
 */
class PlanSelectorController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(
        Request $request,
        TestProject $testProject,
        RoleResolver $roleResolver,
    ): Response {
        $user = $this->actingUser($request);

        $this->authorizeView($user, $testProject, $roleResolver);

        $plans = $testProject->testPlans()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $executable = $roleResolver->plansAllowing($user, Ability::ExecuteTests, $plans);
        $visible = $roleResolver->plansAllowing($user, Ability::ViewExecutions, $plans);

        $shown = $plans
            ->filter(fn (TestPlan $plan): bool => $executable->contains($plan) || $visible->contains($plan))
            ->values();

        $gate = Gate::forUser($user);

        return Inertia::render('plan-selector/index', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
            ],
            'plans' => $shown
                ->map(fn (TestPlan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'is_open' => $plan->is_open,
                    'is_public' => $plan->is_public,
                    'can_execute' => $executable->contains($plan),
                    'can_assign' => $gate->allows(Ability::AssignTesters->value, $plan),
                ])
                ->all(),
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeView(User $user, TestProject $project, RoleResolver $roleResolver): void
    {
        $gate = Gate::forUser($user);

        if (
            $gate->allows(Ability::ExecuteTests->value, $project)
            || $gate->allows(Ability::ViewExecutions->value, $project)
            || $this->hasPlanMembership($user, $project)
        ) {
            return;
        }

        $plans = $project->testPlans()->where('is_active', true)->get();

        if (
            $roleResolver->plansAllowing($user, Ability::ExecuteTests, $plans)->isNotEmpty()
            || $roleResolver->plansAllowing($user, Ability::ViewExecutions, $plans)->isNotEmpty()
        ) {
            return;
        }

        throw new AuthorizationException;
    }

    private function hasPlanMembership(User $user, TestProject $project): bool
    {
        return $user->planRoles()
            ->whereIn(
                'test_plan_id',
                $project->testPlans()->select('id'),
            )
            ->exists();
    }
}
