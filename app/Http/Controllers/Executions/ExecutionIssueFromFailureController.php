<?php

namespace App\Http\Controllers\Executions;

use App\Actions\IssueTrackers\CreateIssueFromExecution;
use App\Http\Controllers\Controller;
use App\Models\Execution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Creates a tracker issue from a completed failed or blocked run.
 */
class ExecutionIssueFromFailureController extends Controller
{
    public function store(
        Request $request,
        Execution $execution,
        CreateIssueFromExecution $createIssueFromExecution,
    ): RedirectResponse {
        $createIssueFromExecution($this->actingUser($request), $execution);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue created.')]);

        return back();
    }
}
