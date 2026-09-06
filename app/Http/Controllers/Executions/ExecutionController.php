<?php

namespace App\Http\Controllers\Executions;

use App\Actions\Executions\DeleteExecution;
use App\Actions\Executions\RecordExecution;
use App\Actions\Executions\UpdateExecutionNotes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Executions\RecordExecutionRequest;
use App\Models\Execution;
use App\Models\TestPlanItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExecutionController extends Controller
{
    public function store(
        RecordExecutionRequest $request,
        TestPlanItem $testPlanItem,
        RecordExecution $recordExecution,
    ): RedirectResponse {
        $execution = $recordExecution(
            $this->actingUser($request),
            $testPlanItem,
            $request->build(),
            $request->executionAttributes(),
            $request->shouldComplete(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $execution->is_draft ? __('Draft saved.') : __('Run recorded.'),
        ]);

        return back();
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
}
