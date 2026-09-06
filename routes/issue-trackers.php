<?php

use App\Http\Controllers\IssueTrackers\IssueTrackerConnectionController;
use App\Http\Controllers\IssueTrackers\IssueTrackerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('projects/{testProject}/issue-tracker', [IssueTrackerController::class, 'show'])
        ->name('issue-trackers.show');
    Route::put('projects/{testProject}/issue-tracker', [IssueTrackerController::class, 'update'])
        ->name('issue-trackers.update');
    Route::post('projects/{testProject}/issue-tracker/connection', [IssueTrackerConnectionController::class, 'store'])
        ->name('issue-trackers.connection');
});
