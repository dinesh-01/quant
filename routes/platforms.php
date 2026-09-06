<?php

use App\Http\Controllers\Platforms\PlanPlatformController;
use App\Http\Controllers\Platforms\PlatformController;
use App\Http\Controllers\Platforms\VersionPlatformController;
use Illuminate\Support\Facades\Route;

/*
 * The catalogue is nested under its project, because a platform name only
 * means something inside one and the list has to be reached before any
 * platform exists. Editing binds the platform alone: it already knows its
 * project, and a nested URL would let the two disagree.
 *
 * Plan and version assignment are keyed by the thing being tagged.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('projects/{testProject}/platforms', [PlatformController::class, 'index'])->name('platforms.index');
    Route::get('projects/{testProject}/platforms/create', [PlatformController::class, 'create'])->name('platforms.create');
    Route::post('projects/{testProject}/platforms', [PlatformController::class, 'store'])->name('platforms.store');

    Route::get('platforms/{platform}/edit', [PlatformController::class, 'edit'])->name('platforms.edit');
    Route::put('platforms/{platform}', [PlatformController::class, 'update'])->name('platforms.update');
    Route::delete('platforms/{platform}', [PlatformController::class, 'destroy'])->name('platforms.destroy');

    Route::put('plans/{testPlan}/platforms', [PlanPlatformController::class, 'update'])
        ->name('plans.platforms.update');

    Route::put('test-case-versions/{testCaseVersion}/platforms', [VersionPlatformController::class, 'update'])
        ->name('test-case-versions.platforms.update');
});
