<?php

namespace App\Http\Controllers\Requirements;

use App\Actions\Requirements\LinkRequirementCoverage;
use App\Actions\Requirements\UnlinkRequirementCoverage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requirements\LinkRequirementCoverageRequest;
use App\Models\RequirementCoverage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RequirementCoverageController extends Controller
{
    public function store(
        LinkRequirementCoverageRequest $request,
        LinkRequirementCoverage $linkRequirementCoverage,
    ): RedirectResponse {
        $linkRequirementCoverage(
            $this->actingUser($request),
            $request->requirementVersion(),
            $request->testCaseVersion(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coverage linked.')]);

        return back();
    }

    public function destroy(
        Request $request,
        RequirementCoverage $requirementCoverage,
        UnlinkRequirementCoverage $unlinkRequirementCoverage,
    ): RedirectResponse {
        $unlinkRequirementCoverage($this->actingUser($request), $requirementCoverage);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coverage removed.')]);

        return back();
    }
}
