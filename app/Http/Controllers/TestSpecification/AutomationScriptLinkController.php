<?php

namespace App\Http\Controllers\TestSpecification;

use App\Actions\TestSpecification\LinkAutomationScript;
use App\Actions\TestSpecification\UnlinkAutomationScript;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestSpecification\LinkAutomationScriptRequest;
use App\Models\TestCaseScriptLink;
use App\Models\TestCaseVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AutomationScriptLinkController extends Controller
{
    public function store(
        LinkAutomationScriptRequest $request,
        TestCaseVersion $testCaseVersion,
        LinkAutomationScript $linkAutomationScript,
    ): RedirectResponse {
        $linkAutomationScript(
            $this->actingUser($request),
            $testCaseVersion,
            $request->script(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Script linked.')]);

        return back();
    }

    public function destroy(
        Request $request,
        TestCaseScriptLink $testCaseScriptLink,
        UnlinkAutomationScript $unlinkAutomationScript,
    ): RedirectResponse {
        $unlinkAutomationScript($this->actingUser($request), $testCaseScriptLink);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Script removed.')]);

        return back();
    }
}
