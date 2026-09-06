<?php

use App\Http\Controllers\CodeTrackers\CodeTrackerConnectionController;
use App\Http\Controllers\CodeTrackers\CodeTrackerController;
use App\Http\Controllers\TestSpecification\AutomationScriptLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('projects/{testProject}/code-tracker', [CodeTrackerController::class, 'show'])
        ->name('code-trackers.show');
    Route::put('projects/{testProject}/code-tracker', [CodeTrackerController::class, 'update'])
        ->name('code-trackers.update');
    Route::post('projects/{testProject}/code-tracker/connection', [CodeTrackerConnectionController::class, 'store'])
        ->name('code-trackers.connection');

    Route::post('test-case-versions/{testCaseVersion}/script-links', [AutomationScriptLinkController::class, 'store'])
        ->name('script-links.store');
    Route::delete('script-links/{testCaseScriptLink}', [AutomationScriptLinkController::class, 'destroy'])
        ->name('script-links.destroy');
});
