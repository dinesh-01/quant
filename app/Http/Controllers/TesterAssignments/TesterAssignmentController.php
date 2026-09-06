<?php

namespace App\Http\Controllers\TesterAssignments;

use App\Actions\TesterAssignments\AssignTester;
use App\Actions\TesterAssignments\CopyTesterAssignments;
use App\Actions\TesterAssignments\UnassignTester;
use App\Actions\TesterAssignments\UpdateTesterAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\TesterAssignments\AssignTesterRequest;
use App\Http\Requests\TesterAssignments\CopyTesterAssignmentsRequest;
use App\Http\Requests\TesterAssignments\UpdateTesterAssignmentRequest;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TesterAssignmentController extends Controller
{
    public function store(
        AssignTesterRequest $request,
        TestPlan $testPlan,
        AssignTester $assignTester,
    ): RedirectResponse {
        $item = $request->item();

        abort_unless($item->test_plan_id === $testPlan->getKey(), 404);

        $assignTester(
            $this->actingUser($request),
            $item,
            $request->build(),
            $request->tester(),
            $request->status(),
            $request->deadline(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tester assigned.')]);

        return to_route('plans.show', [
            'testPlan' => $testPlan,
            'build' => $request->integer('build_id'),
        ]);
    }

    public function update(
        UpdateTesterAssignmentRequest $request,
        TesterAssignment $testerAssignment,
        UpdateTesterAssignment $updateTesterAssignment,
    ): RedirectResponse {
        $updateTesterAssignment(
            $this->actingUser($request),
            $testerAssignment,
            $request->assignmentAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment updated.')]);

        return to_route('plans.show', [
            'testPlan' => $testerAssignment->testPlanItem->testPlan,
            'build' => $testerAssignment->build_id,
        ]);
    }

    public function destroy(
        Request $request,
        TesterAssignment $testerAssignment,
        UnassignTester $unassignTester,
    ): RedirectResponse {
        $testerAssignment->loadMissing('testPlanItem.testPlan');

        $plan = $testerAssignment->testPlanItem->testPlan;
        $buildId = $testerAssignment->build_id;

        $unassignTester($this->actingUser($request), $testerAssignment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tester unassigned.')]);

        return to_route('plans.show', [
            'testPlan' => $plan,
            'build' => $buildId,
        ]);
    }

    public function copy(
        CopyTesterAssignmentsRequest $request,
        TestPlan $testPlan,
        CopyTesterAssignments $copyTesterAssignments,
    ): RedirectResponse {
        $source = $request->source();
        $target = $request->target();

        abort_unless(
            $source->test_plan_id === $testPlan->getKey() && $target->test_plan_id === $testPlan->getKey(),
            404,
        );

        $copied = $copyTesterAssignments($this->actingUser($request), $source, $target);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(':count assignment copied.|:count assignments copied.', $copied, ['count' => $copied]),
        ]);

        return to_route('plans.show', [
            'testPlan' => $testPlan,
            'build' => $target->getKey(),
        ]);
    }
}
