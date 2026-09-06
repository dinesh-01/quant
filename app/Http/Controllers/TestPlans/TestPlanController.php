<?php

namespace App\Http\Controllers\TestPlans;

use App\Actions\CustomFields\SaveCustomFieldValues;
use App\Actions\TestPlans\CreateTestPlan;
use App\Actions\TestPlans\DeleteTestPlan;
use App\Actions\TestPlans\UpdateTestPlan;
use App\Concerns\PresentsCustomFields;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestPlans\TestPlanDeleteRequest;
use App\Http\Requests\TestPlans\TestPlanStoreRequest;
use App\Http\Requests\TestPlans\TestPlanUpdateRequest;
use App\Models\TestPlan;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan management within a project.
 *
 * This is the equivalent of legacy's plan management page, gated by
 * `create_test_plans` on the project, and it lists every plan in the project.
 *
 * It is deliberately *not* the plan selector that execution will need. That
 * screen has to show a tester the plans they hold a role for, public or
 * otherwise, and gate on execution abilities rather than on plan management —
 * so it belongs with Phase 5 and 6, alongside a bulk plan resolver equivalent
 * to `RoleResolver::projectsAllowing()`.
 */
class TestPlanController extends Controller
{
    use PresentsCustomFields;

    public function index(Request $request, TestProject $testProject): Response
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::CreateTestPlans->value, $testProject);

        $plans = $testProject->testPlans()
            ->orderBy('name')
            ->get()
            ->map(fn (TestPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'is_active' => $plan->is_active,
                'is_open' => $plan->is_open,
                'is_public' => $plan->is_public,
            ])
            ->all();

        return Inertia::render('test-plans/index', [
            'project' => $this->projectProp($testProject),
            'plans' => array_values($plans),
        ]);
    }

    public function create(Request $request, TestProject $testProject): Response
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::CreateTestPlans->value, $testProject);

        return Inertia::render('test-plans/create', [
            'project' => $this->projectProp($testProject),
        ]);
    }

    public function store(
        TestPlanStoreRequest $request,
        TestProject $testProject,
        CreateTestPlan $createTestPlan,
    ): RedirectResponse {
        $createTestPlan($this->actingUser($request), $testProject, $request->planAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test plan created.')]);

        return to_route('plans.index', $testProject);
    }

    public function edit(Request $request, TestPlan $testPlan): Response
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::CreateTestPlans->value, $testPlan);

        return Inertia::render('test-plans/edit', [
            'project' => $this->projectProp($testPlan->testProject),
            'plan' => [
                'id' => $testPlan->id,
                'name' => $testPlan->name,
                'description' => $testPlan->description,
                'is_active' => $testPlan->is_active,
                'is_open' => $testPlan->is_open,
                'is_public' => $testPlan->is_public,
            ],
            'customFields' => $this->customFieldProps($testPlan),
        ]);
    }

    public function update(
        TestPlanUpdateRequest $request,
        TestPlan $testPlan,
        UpdateTestPlan $updateTestPlan,
        SaveCustomFieldValues $saveCustomFieldValues,
    ): RedirectResponse {
        /*
         * The answers go in first here, unlike the specification screens, and
         * that order matters: a plan is its own authorization scope, so an edit
         * that turns off "open to everyone" leaves a project-role planner
         * without access to the plan they have just saved. Writing the answers
         * afterwards would be refused by that new state — the same request
         * locking itself out halfway through. Both steps check the same
         * ability, so nothing is written that the update would have refused.
         */
        $saveCustomFieldValues($this->actingUser($request), $testPlan, $request->customFieldAnswers());

        $updateTestPlan($this->actingUser($request), $testPlan, $request->planAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test plan updated.')]);

        return to_route('plans.index', $testPlan->testProject);
    }

    public function destroy(
        TestPlanDeleteRequest $request,
        TestPlan $testPlan,
        DeleteTestPlan $deleteTestPlan,
    ): RedirectResponse {
        $project = $testPlan->testProject;

        $deleteTestPlan($this->actingUser($request), $testPlan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test plan deleted.')]);

        return to_route('plans.index', $project);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function projectProp(TestProject $project): array
    {
        return ['id' => $project->id, 'name' => $project->name];
    }
}
