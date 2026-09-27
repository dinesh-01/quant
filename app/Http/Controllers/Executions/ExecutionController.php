<?php

namespace App\Http\Controllers\Executions;

use App\Actions\Executions\DeleteExecution;
use App\Actions\Executions\RecordExecution;
use App\Actions\Executions\ResolveNextRunItem;
use App\Actions\Executions\UpdateExecutionNotes;
use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Executions\RecordExecutionRequest;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ExecutionController extends Controller
{
    public function store(
        RecordExecutionRequest $request,
        TestPlanItem $testPlanItem,
        RecordExecution $recordExecution,
        ResolveNextRunItem $nextRunItem,
    ): RedirectResponse {
        $user = $this->actingUser($request);
        $testPlanItem->loadMissing('testPlan');
        $build = $request->build();
        $status = $request->executionAttributes()['status'];

        if ($request->shouldComplete() && $status === ExecutionStatus::NotRun) {
            Gate::forUser($user)->authorize(Ability::ExecuteTests->value, $testPlanItem->testPlan);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Case skipped.'),
            ]);

            return $this->redirectAfterSubmit(
                $user,
                $testPlanItem,
                $build,
                $status,
                $nextRunItem,
            );
        }

        $execution = $recordExecution(
            $user,
            $testPlanItem,
            $build,
            $request->executionAttributes(),
            $request->shouldComplete(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $execution->is_draft ? __('Draft saved.') : __('Run recorded.'),
        ]);

        if ($execution->is_draft) {
            return back();
        }

        return $this->redirectAfterSubmit(
            $user,
            $testPlanItem,
            $build,
            $execution->status,
            $nextRunItem,
        );
    }

    public function destroy(
        Request $request,
        Execution $execution,
        DeleteExecution $deleteExecution,
    ): RedirectResponse {
        $deleteExecution($this->actingUser($request), $execution);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Run deleted.')]);

        return back();
    }

    public function updateNotes(
        Request $request,
        Execution $execution,
        UpdateExecutionNotes $updateExecutionNotes,
    ): RedirectResponse {
        $request->validate(['notes' => ['nullable', 'string']]);

        $updateExecutionNotes(
            $this->actingUser($request),
            $execution,
            $request->filled('notes') ? (string) $request->input('notes') : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notes saved.')]);

        return back();
    }

    private function redirectAfterSubmit(
        User $user,
        TestPlanItem $item,
        Build $build,
        ExecutionStatus $status,
        ResolveNextRunItem $nextRunItem,
    ): RedirectResponse {
        if (in_array($status, [ExecutionStatus::Failed, ExecutionStatus::Blocked], true)) {
            return redirect()->route('executions.show', [
                'testPlan' => $item->testPlan,
                'testPlanItem' => $item,
                'build' => $build->id,
                'issue' => 1,
            ]);
        }

        $next = $nextRunItem($user, $item->testPlan, $build, $item);

        if ($next === null) {
            return redirect()->route('executions.index', [
                'testPlan' => $item->testPlan,
                'build' => $build->id,
            ]);
        }

        return redirect()->route('executions.show', [
            'testPlan' => $item->testPlan,
            'testPlanItem' => $next,
            'build' => $build->id,
        ]);
    }
}
