<?php

namespace App\Http\Controllers\Requirements;

use App\Actions\Requirements\UnwatchRequirement;
use App\Actions\Requirements\WatchRequirement;
use App\Http\Controllers\Controller;
use App\Models\Requirement;
use App\Models\RequirementMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RequirementMonitorController extends Controller
{
    public function store(
        Request $request,
        Requirement $requirement,
        WatchRequirement $watchRequirement,
    ): RedirectResponse {
        $watchRequirement($this->actingUser($request), $requirement);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Watching this requirement.')]);

        return back();
    }

    public function destroy(
        Request $request,
        RequirementMonitor $requirementMonitor,
        UnwatchRequirement $unwatchRequirement,
    ): RedirectResponse {
        $unwatchRequirement($this->actingUser($request), $requirementMonitor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stopped watching.')]);

        return back();
    }
}
