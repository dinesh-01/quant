<?php

namespace App\Http\Controllers\IssueTrackers;

use App\Actions\IssueTrackers\TestIssueTrackerConnection;
use App\Http\Controllers\Controller;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Probes the saved tracker. A draft URL has to be saved first.
 */
class IssueTrackerConnectionController extends Controller
{
    public function store(
        Request $request,
        TestProject $testProject,
        TestIssueTrackerConnection $testIssueTrackerConnection,
    ): RedirectResponse {
        $result = $testIssueTrackerConnection($this->actingUser($request), $testProject);

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }
}
