<?php

namespace App\Http\Controllers\Requirements;

use App\Actions\Requirements\CreateRequirement;
use App\Actions\Requirements\DeleteRequirement;
use App\Actions\Requirements\MoveRequirement;
use App\Actions\Requirements\UpdateRequirement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requirements\MoveRequirementRequest;
use App\Http\Requests\Requirements\StoreRequirementRequest;
use App\Http\Requests\Requirements\UpdateRequirementRequest;
use App\Models\Requirement;
use App\Models\RequirementSpec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RequirementController extends Controller
{
    public function store(
        StoreRequirementRequest $request,
        RequirementSpec $requirementSpec,
        CreateRequirement $createRequirement,
    ): RedirectResponse {
        $requirement = $createRequirement(
            $this->actingUser($request),
            $requirementSpec,
            $request->requirementAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requirement created.')]);

        return to_route('requirements.items.show', [
            $requirement->test_project_id,
            $requirement,
        ]);
    }

    public function update(
        UpdateRequirementRequest $request,
        Requirement $requirement,
        UpdateRequirement $updateRequirement,
    ): RedirectResponse {
        $updateRequirement($this->actingUser($request), $requirement, $request->requirementAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requirement saved.')]);

        return back();
    }

    public function destroy(
        Request $request,
        Requirement $requirement,
        DeleteRequirement $deleteRequirement,
    ): RedirectResponse {
        $projectId = $requirement->test_project_id;
        $specId = $requirement->requirement_spec_id;

        $deleteRequirement($this->actingUser($request), $requirement);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requirement deleted.')]);

        return to_route('requirements.specs.show', [$projectId, $specId]);
    }

    public function move(
        MoveRequirementRequest $request,
        Requirement $requirement,
        MoveRequirement $moveRequirement,
    ): RedirectResponse {
        $moveRequirement($this->actingUser($request), $requirement, $request->targetSpec());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requirement moved.')]);

        return back();
    }
}
