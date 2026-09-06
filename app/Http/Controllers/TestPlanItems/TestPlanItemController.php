<?php

namespace App\Http\Controllers\TestPlanItems;

use App\Actions\TestPlanItems\LinkTestPlanItem;
use App\Actions\TestPlanItems\ReorderTestPlanItems;
use App\Actions\TestPlanItems\SetTestPlanItemUrgency;
use App\Actions\TestPlanItems\UnlinkTestPlanItem;
use App\Actions\TestPlanItems\UpdateLinkedTestCaseVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestPlanItems\LinkTestPlanItemRequest;
use App\Http\Requests\TestPlanItems\ReorderTestPlanItemsRequest;
use App\Http\Requests\TestPlanItems\SetTestPlanItemUrgencyRequest;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TestPlanItemController extends Controller
{
    public function store(
        LinkTestPlanItemRequest $request,
        TestPlan $testPlan,
        LinkTestPlanItem $linkTestPlanItem,
    ): RedirectResponse {
        $linkTestPlanItem(
            $this->actingUser($request),
            $testPlan,
            $request->version(),
            $request->platform(),
            $request->urgency(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test case linked.')]);

        return to_route('plans.show', $testPlan);
    }

    public function destroy(
        Request $request,
        TestPlanItem $testPlanItem,
        UnlinkTestPlanItem $unlinkTestPlanItem,
    ): RedirectResponse {
        $plan = $testPlanItem->testPlan;

        $unlinkTestPlanItem($this->actingUser($request), $testPlanItem);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test case unlinked.')]);

        return to_route('plans.show', $plan);
    }

    public function updateUrgency(
        SetTestPlanItemUrgencyRequest $request,
        TestPlanItem $testPlanItem,
        SetTestPlanItemUrgency $setTestPlanItemUrgency,
    ): RedirectResponse {
        $setTestPlanItemUrgency($this->actingUser($request), $testPlanItem, $request->urgency());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Urgency updated.')]);

        return to_route('plans.show', $testPlanItem->testPlan);
    }

    public function updateVersion(
        Request $request,
        TestPlanItem $testPlanItem,
        UpdateLinkedTestCaseVersion $updateLinkedTestCaseVersion,
    ): RedirectResponse {
        $updateLinkedTestCaseVersion($this->actingUser($request), $testPlanItem);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Linked version updated.')]);

        return to_route('plans.show', $testPlanItem->testPlan);
    }

    public function reorder(
        ReorderTestPlanItemsRequest $request,
        TestPlan $testPlan,
        ReorderTestPlanItems $reorderTestPlanItems,
    ): RedirectResponse {
        $reorderTestPlanItems($this->actingUser($request), $testPlan, $request->orderedIds());

        return to_route('plans.show', $testPlan);
    }
}
