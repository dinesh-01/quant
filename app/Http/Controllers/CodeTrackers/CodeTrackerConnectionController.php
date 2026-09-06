<?php

namespace App\Http\Controllers\CodeTrackers;

use App\Actions\CodeTrackers\TestCodeTrackerConnection;
use App\Http\Controllers\Controller;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Probes the saved tracker. A draft URL has to be saved first.
 */
class CodeTrackerConnectionController extends Controller
{
    public function store(
        Request $request,
        TestProject $testProject,
        TestCodeTrackerConnection $testCodeTrackerConnection,
    ): RedirectResponse {
        $result = $testCodeTrackerConnection($this->actingUser($request), $testProject);

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }
}
