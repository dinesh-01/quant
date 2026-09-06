<?php

namespace App\Http\Controllers\Platforms;

use App\Actions\Platforms\SyncVersionPlatforms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platforms\SyncVersionPlatformsRequest;
use App\Models\TestCaseVersion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VersionPlatformController extends Controller
{
    public function update(
        SyncVersionPlatformsRequest $request,
        TestCaseVersion $testCaseVersion,
        SyncVersionPlatforms $syncVersionPlatforms,
    ): RedirectResponse {
        $syncVersionPlatforms($this->actingUser($request), $testCaseVersion, $request->platformIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version platforms updated.')]);

        return back();
    }
}
