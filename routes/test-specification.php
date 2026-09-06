<?php

use App\Http\Controllers\TestSpecification\TestCaseController;
use App\Http\Controllers\TestSpecification\TestCaseRelationController;
use App\Http\Controllers\TestSpecification\TestCaseStepController;
use App\Http\Controllers\TestSpecification\TestCaseVersionController;
use App\Http\Controllers\TestSpecification\TestSpecificationController;
use App\Http\Controllers\TestSpecification\TestSuiteController;
use Illuminate\Support\Facades\Route;

/*
 * The read routes are nested under a project so every node has a deep link and
 * so the project — which is the authorization scope for this whole area — is
 * always resolved from the URL rather than a session value. Legacy kept the
 * "current project" in the session, which made links unshareable.
 *
 * Write routes are keyed by the node alone: a suite, case, version or step
 * already knows its project, and repeating it in the URL only creates a
 * mismatch to validate.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('projects/{testProject}/specification')->group(function () {
        Route::get('/', [TestSpecificationController::class, 'show'])
            ->name('specification.show');

        Route::get('search', [TestSpecificationController::class, 'search'])
            ->name('specification.search');

        Route::get('copy-targets', [TestSpecificationController::class, 'copyTargets'])
            ->name('specification.copy-targets');

        Route::get('suites/{testSuite}', [TestSpecificationController::class, 'showSuite'])
            ->scopeBindings()
            ->name('specification.suites.show');

        Route::get('cases/{testCase}', [TestSpecificationController::class, 'showCase'])
            ->scopeBindings()
            ->name('specification.cases.show');

        Route::post('suites', [TestSuiteController::class, 'store'])
            ->name('test-suites.store');

        Route::post('suites/reorder', [TestSuiteController::class, 'reorder'])
            ->name('test-suites.reorder');
    });

    Route::put('test-suites/{testSuite}', [TestSuiteController::class, 'update'])
        ->name('test-suites.update');
    Route::delete('test-suites/{testSuite}', [TestSuiteController::class, 'destroy'])
        ->name('test-suites.destroy');
    Route::post('test-suites/{testSuite}/move', [TestSuiteController::class, 'move'])
        ->name('test-suites.move');
    Route::post('test-suites/{testSuite}/copy', [TestSuiteController::class, 'copy'])
        ->name('test-suites.copy');

    Route::post('test-suites/{testSuite}/cases', [TestCaseController::class, 'store'])
        ->name('test-cases.store');
    Route::post('test-suites/{testSuite}/cases/reorder', [TestCaseController::class, 'reorder'])
        ->name('test-cases.reorder');

    Route::put('test-cases/{testCase}', [TestCaseController::class, 'update'])
        ->name('test-cases.update');
    Route::delete('test-cases/{testCase}', [TestCaseController::class, 'destroy'])
        ->name('test-cases.destroy');
    Route::post('test-cases/{testCase}/move', [TestCaseController::class, 'move'])
        ->name('test-cases.move');
    Route::post('test-cases/{testCase}/copy', [TestCaseController::class, 'copy'])
        ->name('test-cases.copy');

    Route::post('test-cases/{testCase}/relations', [TestCaseRelationController::class, 'store'])
        ->name('test-case-relations.store');
    Route::delete('test-case-relations/{testCaseRelation}', [TestCaseRelationController::class, 'destroy'])
        ->name('test-case-relations.destroy');

    Route::post('test-cases/{testCase}/versions', [TestCaseVersionController::class, 'store'])
        ->name('test-case-versions.store');
    Route::put('test-case-versions/{testCaseVersion}', [TestCaseVersionController::class, 'update'])
        ->name('test-case-versions.update');
    Route::delete('test-case-versions/{testCaseVersion}', [TestCaseVersionController::class, 'destroy'])
        ->name('test-case-versions.destroy');
    Route::post('test-case-versions/{testCaseVersion}/freeze', [TestCaseVersionController::class, 'freeze'])
        ->name('test-case-versions.freeze');
    Route::post('test-case-versions/{testCaseVersion}/unfreeze', [TestCaseVersionController::class, 'unfreeze'])
        ->name('test-case-versions.unfreeze');

    Route::post('test-case-versions/{testCaseVersion}/steps', [TestCaseStepController::class, 'store'])
        ->name('test-case-steps.store');
    Route::post('test-case-versions/{testCaseVersion}/steps/reorder', [TestCaseStepController::class, 'reorder'])
        ->name('test-case-steps.reorder');
    Route::put('test-case-steps/{testCaseStep}', [TestCaseStepController::class, 'update'])
        ->name('test-case-steps.update');
    Route::delete('test-case-steps/{testCaseStep}', [TestCaseStepController::class, 'destroy'])
        ->name('test-case-steps.destroy');
});
