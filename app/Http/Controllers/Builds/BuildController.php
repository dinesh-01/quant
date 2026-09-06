<?php

namespace App\Http\Controllers\Builds;

use App\Actions\Builds\CreateBuild;
use App\Actions\Builds\DeleteBuild;
use App\Actions\Builds\UpdateBuild;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Builds\BuildStoreRequest;
use App\Http\Requests\Builds\BuildUpdateRequest;
use App\Models\Build;
use App\Models\TestPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Builds on a plan.
 *
 * Create is nested under the plan because no build exists yet. Editing binds
 * the build alone: it already knows its plan, and a nested URL would let the
 * two disagree.
 */
class BuildController extends Controller
{
    public function create(Request $request, TestPlan $testPlan): Response
    {
        $testPlan->loadMissing('testProject');

        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ManageBuilds->value, $testPlan);

        return Inertia::render('builds/create', [
            'project' => [
                'id' => $testPlan->testProject->id,
                'name' => $testPlan->testProject->name,
            ],
            'plan' => [
                'id' => $testPlan->id,
                'name' => $testPlan->name,
            ],
        ]);
    }

    public function store(
        BuildStoreRequest $request,
        TestPlan $testPlan,
        CreateBuild $createBuild,
    ): RedirectResponse {
        $createBuild($this->actingUser($request), $testPlan, $request->build());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Build created.')]);

        return to_route('plans.show', $testPlan);
    }

    public function edit(Request $request, Build $build): Response
    {
        $build->loadMissing('testPlan.testProject');

        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ManageBuilds->value, $build->testPlan);

        return Inertia::render('builds/edit', [
            'project' => [
                'id' => $build->testPlan->testProject->id,
                'name' => $build->testPlan->testProject->name,
            ],
            'plan' => [
                'id' => $build->testPlan->id,
                'name' => $build->testPlan->name,
            ],
            'build' => [
                'id' => $build->id,
                'name' => $build->name,
                'notes' => $build->notes,
                'is_active' => $build->is_active,
                'is_open' => $build->is_open,
                'release_date' => $build->release_date?->toDateString(),
            ],
        ]);
    }

    public function update(
        BuildUpdateRequest $request,
        Build $build,
        UpdateBuild $updateBuild,
    ): RedirectResponse {
        $updateBuild($this->actingUser($request), $build, $request->build());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Build updated.')]);

        return to_route('plans.show', $build->testPlan);
    }

    public function destroy(
        Request $request,
        Build $build,
        DeleteBuild $deleteBuild,
    ): RedirectResponse {
        $plan = $build->testPlan;
        $name = $build->name;

        $deleteBuild($this->actingUser($request), $build);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('":name" deleted.', ['name' => $name]),
        ]);

        return to_route('plans.show', $plan);
    }
}
