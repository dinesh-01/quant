<?php

use App\Http\Controllers\Attachments\AttachmentController;
use Illuminate\Support\Facades\Route;

/*
 * Uploads are keyed by the parent, one route per type, so the target can only
 * ever be something the router resolved. Reads and deletes are keyed by the
 * attachment alone, which already knows its parent — the same shape the
 * specification write routes use.
 *
 * There is no index route. Attachments are listed as part of whatever they hang
 * off, so a listing would need the parent's authorization all over again for no
 * screen that wants it.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('projects/{testProject}/attachments', [AttachmentController::class, 'storeForProject'])
        ->name('attachments.projects.store');

    Route::post('test-suites/{testSuite}/attachments', [AttachmentController::class, 'storeForSuite'])
        ->name('attachments.suites.store');

    Route::post('test-case-versions/{testCaseVersion}/attachments', [AttachmentController::class, 'storeForVersion'])
        ->name('attachments.versions.store');

    Route::post('plans/{testPlan}/attachments', [AttachmentController::class, 'storeForPlan'])
        ->name('attachments.plans.store');

    Route::post('executions/{execution}/attachments', [AttachmentController::class, 'storeForExecution'])
        ->name('attachments.executions.store');

    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])
        ->name('attachments.show');

    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('attachments.destroy');
});
