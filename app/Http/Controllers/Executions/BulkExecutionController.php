<?php

namespace App\Http\Controllers\Executions;

use App\Actions\Executions\BulkRecordExecutions;
use App\Enums\ExecutionStatus;
use App\Http\Controllers\Controller;
use App\Models\Build;
use App\Models\TestPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BulkExecutionController extends Controller
{
    public function store(
        Request $request,
        TestPlan $testPlan,
        BulkRecordExecutions $bulkRecordExecutions,
    ): RedirectResponse {
        $validated = $request->validate([
            'build_id' => ['required', 'integer', 'exists:builds,id'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:test_plan_items,id'],
            'status' => ['required', Rule::enum(ExecutionStatus::class)],
        ]);

        $recorded = $bulkRecordExecutions(
            $this->actingUser($request),
            $testPlan,
            Build::query()->findOrFail($validated['build_id']),
            array_map(intval(...), $validated['item_ids']),
            ExecutionStatus::from($validated['status']),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count runs recorded.', ['count' => count($recorded)]),
        ]);

        return back();
    }
}
