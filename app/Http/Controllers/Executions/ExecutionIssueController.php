<?php

namespace App\Http\Controllers\Executions;

use App\Actions\Executions\LinkExecutionIssue;
use App\Actions\Executions\UnlinkExecutionIssue;
use App\Http\Controllers\Controller;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExecutionIssueController extends Controller
{
    public function store(
        Request $request,
        Execution $execution,
        LinkExecutionIssue $linkExecutionIssue,
    ): RedirectResponse {
        $request->validate(['issue_id' => ['required', 'string', 'max:64']]);

        $linkExecutionIssue(
            $this->actingUser($request),
            $execution,
            (string) $request->input('issue_id'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue linked.')]);

        return back();
    }

    public function destroy(
        Request $request,
        ExecutionIssue $executionIssue,
        UnlinkExecutionIssue $unlinkExecutionIssue,
    ): RedirectResponse {
        $unlinkExecutionIssue($this->actingUser($request), $executionIssue);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue removed.')]);

        return back();
    }
}
