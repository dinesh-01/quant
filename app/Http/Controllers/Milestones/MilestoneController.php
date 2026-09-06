<?php

namespace App\Http\Controllers\Milestones;

use App\Actions\Milestones\CreateMilestone;
use App\Actions\Milestones\DeleteMilestone;
use App\Actions\Milestones\UpdateMilestone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Milestones\MilestoneRequest;
use App\Models\Milestone;
use App\Models\TestPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MilestoneController extends Controller
{
    public function store(
        MilestoneRequest $request,
        TestPlan $testPlan,
        CreateMilestone $createMilestone,
    ): RedirectResponse {
        $createMilestone($this->actingUser($request), $testPlan, $request->milestoneAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Milestone added.')]);

        return back();
    }

    public function update(
        MilestoneRequest $request,
        Milestone $milestone,
        UpdateMilestone $updateMilestone,
    ): RedirectResponse {
        $updateMilestone($this->actingUser($request), $milestone, $request->milestoneAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Milestone saved.')]);

        return back();
    }

    public function destroy(
        Request $request,
        Milestone $milestone,
        DeleteMilestone $deleteMilestone,
    ): RedirectResponse {
        $deleteMilestone($this->actingUser($request), $milestone);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Milestone removed.')]);

        return back();
    }
}
