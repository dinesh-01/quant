<?php

use App\Http\Controllers\Requirements\RequirementController;
use App\Http\Controllers\Requirements\RequirementCoverageController;
use App\Http\Controllers\Requirements\RequirementMonitorController;
use App\Http\Controllers\Requirements\RequirementsController;
use App\Http\Controllers\Requirements\RequirementSpecController;
use App\Http\Controllers\Requirements\RequirementVersionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('projects/{testProject}/requirements')->group(function () {
        Route::get('/', [RequirementsController::class, 'show'])
            ->name('requirements.show');

        Route::get('specs/{requirementSpec}', [RequirementsController::class, 'showSpec'])
            ->scopeBindings()
            ->name('requirements.specs.show');

        Route::get('items/{requirement}', [RequirementsController::class, 'showRequirement'])
            ->scopeBindings()
            ->name('requirements.items.show');

        Route::post('specs', [RequirementSpecController::class, 'store'])
            ->name('requirement-specs.store');
    });

    Route::post('requirements/{requirement}/watch', [RequirementMonitorController::class, 'store'])
        ->name('requirement-monitors.store');
    Route::delete('requirement-monitors/{requirementMonitor}', [RequirementMonitorController::class, 'destroy'])
        ->name('requirement-monitors.destroy');

    Route::put('requirement-specs/{requirementSpec}', [RequirementSpecController::class, 'update'])
        ->name('requirement-specs.update');
    Route::delete('requirement-specs/{requirementSpec}', [RequirementSpecController::class, 'destroy'])
        ->name('requirement-specs.destroy');
    Route::post('requirement-specs/{requirementSpec}/move', [RequirementSpecController::class, 'move'])
        ->name('requirement-specs.move');

    Route::post('requirement-specs/{requirementSpec}/requirements', [RequirementController::class, 'store'])
        ->name('requirements.store');
    Route::put('requirements/{requirement}', [RequirementController::class, 'update'])
        ->name('requirements.update');
    Route::delete('requirements/{requirement}', [RequirementController::class, 'destroy'])
        ->name('requirements.destroy');
    Route::post('requirements/{requirement}/move', [RequirementController::class, 'move'])
        ->name('requirements.move');

    Route::post('requirements/{requirement}/versions', [RequirementVersionController::class, 'store'])
        ->name('requirement-versions.store');
    Route::put('requirement-versions/{requirementVersion}', [RequirementVersionController::class, 'update'])
        ->name('requirement-versions.update');
    Route::post('requirement-versions/{requirementVersion}/freeze', [RequirementVersionController::class, 'freeze'])
        ->name('requirement-versions.freeze');
    Route::post('requirement-versions/{requirementVersion}/unfreeze', [RequirementVersionController::class, 'unfreeze'])
        ->name('requirement-versions.unfreeze');

    Route::post('requirement-versions/{requirementVersion}/coverage', [RequirementCoverageController::class, 'store'])
        ->name('requirement-coverages.store');
    Route::post('test-case-versions/{testCaseVersion}/coverage', [RequirementCoverageController::class, 'store'])
        ->name('test-case-coverages.store');
    Route::delete('requirement-coverages/{requirementCoverage}', [RequirementCoverageController::class, 'destroy'])
        ->name('requirement-coverages.destroy');
});
