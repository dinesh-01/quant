<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\CaseController;
use App\Http\Controllers\Api\V1\ExecutionController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PlanBuildController;
use App\Http\Controllers\Api\V1\PlanCaseController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\ProjectCaseController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectPlanController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
 * Fresh /api/v1 — not a shim of TestLink's XML-RPC or /lib/api/rest/v3.
 * Tokens inherit the user's Role/Ability gates; there is no second ACL.
 * EnsureUserIsActive must sit after auth:sanctum so $request->user() is set,
 * and it must not invalidate a session on a token request.
 */
Route::middleware(['auth:sanctum', EnsureUserIsActive::class, 'throttle:api'])->group(function () {
    Route::get('me', [MeController::class, 'show'])->name('api.v1.me');

    Route::get('projects', [ProjectController::class, 'index'])->name('api.v1.projects.index');
    Route::get('projects/{testProject}', [ProjectController::class, 'show'])->name('api.v1.projects.show');
    Route::get('projects/{testProject}/cases', [ProjectCaseController::class, 'index'])->name('api.v1.projects.cases.index');
    Route::get('projects/{testProject}/plans', [ProjectPlanController::class, 'index'])->name('api.v1.projects.plans.index');

    Route::post('projects/{testProject}/suites/{testSuite}/cases', [CaseController::class, 'store'])
        ->scopeBindings()
        ->name('api.v1.cases.store');

    Route::get('cases/{testCase}', [CaseController::class, 'show'])->name('api.v1.cases.show');

    Route::get('plans/{testPlan}', [PlanController::class, 'show'])->name('api.v1.plans.show');
    Route::get('plans/{testPlan}/cases', [PlanCaseController::class, 'index'])->name('api.v1.plans.cases.index');
    Route::get('plans/{testPlan}/builds', [PlanBuildController::class, 'index'])->name('api.v1.plans.builds.index');
    Route::post('plans/{testPlan}/executions', [ExecutionController::class, 'store'])->name('api.v1.executions.store');

    Route::post('projects/{testProject}/attachments', [AttachmentController::class, 'storeForProject'])
        ->name('api.v1.attachments.projects.store');
    Route::post('suites/{testSuite}/attachments', [AttachmentController::class, 'storeForSuite'])
        ->name('api.v1.attachments.suites.store');
    Route::post('versions/{testCaseVersion}/attachments', [AttachmentController::class, 'storeForVersion'])
        ->name('api.v1.attachments.versions.store');
    Route::post('plans/{testPlan}/attachments', [AttachmentController::class, 'storeForPlan'])
        ->name('api.v1.attachments.plans.store');
    Route::post('executions/{execution}/attachments', [AttachmentController::class, 'storeForExecution'])
        ->name('api.v1.attachments.executions.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])
        ->name('api.v1.attachments.show');
});
