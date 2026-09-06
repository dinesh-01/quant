<?php

namespace App\Http\Controllers\Platforms;

use App\Actions\Platforms\SyncPlanPlatforms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platforms\SyncPlanPlatformsRequest;
use App\Models\TestPlan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlanPlatformController extends Controller
{
    public function update(
        SyncPlanPlatformsRequest $request,
        TestPlan $testPlan,
        SyncPlanPlatforms $syncPlanPlatforms,
    ): RedirectResponse {
        $syncPlanPlatforms($this->actingUser($request), $testPlan, $request->platformIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan platforms updated.')]);

        return to_route('plans.show', $testPlan);
    }
}
