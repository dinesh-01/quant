<?php

namespace App\Http\Controllers\Requirements;

use App\Actions\Requirements\CreateRequirementSpec;
use App\Actions\Requirements\DeleteRequirementSpec;
use App\Actions\Requirements\MoveRequirementSpec;
use App\Actions\Requirements\UpdateRequirementSpec;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requirements\MoveRequirementSpecRequest;
use App\Http\Requests\Requirements\StoreRequirementSpecRequest;
use App\Http\Requests\Requirements\UpdateRequirementSpecRequest;
use App\Models\RequirementSpec;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RequirementSpecController extends Controller
{
    public function store(
        StoreRequirementSpecRequest $request,
        TestProject $testProject,
        CreateRequirementSpec $createRequirementSpec,
    ): RedirectResponse {
        $parentId = $request->parentSpecId();
        $parent = $parentId === null ? null : RequirementSpec::query()->findOrFail($parentId);

        $spec = $createRequirementSpec(
            $this->actingUser($request),
            $testProject,
            $parent,
            $request->specAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Specification created.')]);

        return to_route('requirements.specs.show', [$testProject, $spec]);
    }

    public function update(
        UpdateRequirementSpecRequest $request,
        RequirementSpec $requirementSpec,
        UpdateRequirementSpec $updateRequirementSpec,
    ): RedirectResponse {
        $updateRequirementSpec($this->actingUser($request), $requirementSpec, $request->specAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Specification saved.')]);

        return back();
    }

    public function destroy(
        Request $request,
        RequirementSpec $requirementSpec,
        DeleteRequirementSpec $deleteRequirementSpec,
    ): RedirectResponse {
        $project = $requirementSpec->testProject;

        $deleteRequirementSpec($this->actingUser($request), $requirementSpec);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Specification deleted.')]);

        return to_route('requirements.show', $project);
    }

    public function move(
        MoveRequirementSpecRequest $request,
        RequirementSpec $requirementSpec,
        MoveRequirementSpec $moveRequirementSpec,
    ): RedirectResponse {
        $moveRequirementSpec($this->actingUser($request), $requirementSpec, $request->parentSpec());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Specification moved.')]);

        return back();
    }
}
