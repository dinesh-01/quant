<?php

namespace App\Http\Controllers\Requirements;

use App\Actions\Requirements\CreateRequirementVersion;
use App\Actions\Requirements\FreezeRequirementVersion;
use App\Actions\Requirements\UnfreezeRequirementVersion;
use App\Actions\Requirements\UpdateRequirementVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requirements\UpdateRequirementVersionRequest;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RequirementVersionController extends Controller
{
    public function store(
        Request $request,
        Requirement $requirement,
        CreateRequirementVersion $createRequirementVersion,
    ): RedirectResponse {
        $version = $createRequirementVersion($this->actingUser($request), $requirement);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Version :number created.', ['number' => $version->version]),
        ]);

        return back();
    }

    public function update(
        UpdateRequirementVersionRequest $request,
        RequirementVersion $requirementVersion,
        UpdateRequirementVersion $updateRequirementVersion,
    ): RedirectResponse {
        $updateRequirementVersion(
            $this->actingUser($request),
            $requirementVersion,
            $request->versionAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requirement saved.')]);

        return back();
    }

    public function freeze(
        Request $request,
        RequirementVersion $requirementVersion,
        FreezeRequirementVersion $freezeRequirementVersion,
    ): RedirectResponse {
        $freezeRequirementVersion($this->actingUser($request), $requirementVersion);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version frozen.')]);

        return back();
    }

    public function unfreeze(
        Request $request,
        RequirementVersion $requirementVersion,
        UnfreezeRequirementVersion $unfreezeRequirementVersion,
    ): RedirectResponse {
        $unfreezeRequirementVersion($this->actingUser($request), $requirementVersion);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version reopened.')]);

        return back();
    }
}
