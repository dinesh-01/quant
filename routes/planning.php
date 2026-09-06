<?php

use App\Http\Controllers\Builds\BuildController;
use App\Http\Controllers\Executions\BulkExecutionController;
use App\Http\Controllers\Executions\ExecutionController;
use App\Http\Controllers\Executions\ExecutionIssueController;
use App\Http\Controllers\Executions\ExecutionIssueFromFailureController;
use App\Http\Controllers\Executions\ExecutionNavigatorController;
use App\Http\Controllers\Milestones\MilestoneController;
use App\Http\Controllers\PlanSelector\PlanSelectorController;
use App\Http\Controllers\TesterAssignments\TesterAssignmentController;
use App\Http\Controllers\TestPlanItems\TestPlanItemController;
use App\Http\Controllers\TestPlans\TestPlanContentsController;
use Illuminate\Support\Facades\Route;

/*
 * The plan as a workspace, plus the builds and items that hang off it.
 *
 * The contents page binds the plan alone. Create-build is nested under the
 * plan because no build exists yet; editing a build binds the build.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('plans/{testPlan}', [TestPlanContentsController::class, 'show'])->name('plans.show');

    Route::get('plans/{testPlan}/builds/create', [BuildController::class, 'create'])->name('builds.create');
    Route::post('plans/{testPlan}/builds', [BuildController::class, 'store'])->name('builds.store');
    Route::get('builds/{build}/edit', [BuildController::class, 'edit'])->name('builds.edit');
    Route::put('builds/{build}', [BuildController::class, 'update'])->name('builds.update');
    Route::delete('builds/{build}', [BuildController::class, 'destroy'])->name('builds.destroy');

    Route::post('plans/{testPlan}/milestones', [MilestoneController::class, 'store'])->name('milestones.store');
    Route::put('milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');
    Route::delete('milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('milestones.destroy');

    Route::post('plans/{testPlan}/executions/bulk', [BulkExecutionController::class, 'store'])->name('executions.bulk');

    Route::post('plans/{testPlan}/items', [TestPlanItemController::class, 'store'])->name('plan-items.store');
    Route::post('plans/{testPlan}/items/reorder', [TestPlanItemController::class, 'reorder'])->name('plan-items.reorder');
    Route::put('plan-items/{testPlanItem}/urgency', [TestPlanItemController::class, 'updateUrgency'])->name('plan-items.urgency');
    Route::put('plan-items/{testPlanItem}/version', [TestPlanItemController::class, 'updateVersion'])->name('plan-items.version');
    Route::delete('plan-items/{testPlanItem}', [TestPlanItemController::class, 'destroy'])->name('plan-items.destroy');

    Route::get('projects/{testProject}/execute', [PlanSelectorController::class, 'index'])->name('plan-selector.index');

    Route::get('plans/{testPlan}/execute', [ExecutionNavigatorController::class, 'index'])->name('executions.index');
    Route::get('plans/{testPlan}/execute/items/{testPlanItem}', [ExecutionNavigatorController::class, 'show'])
        ->name('executions.show');
    Route::post('plan-items/{testPlanItem}/executions', [ExecutionController::class, 'store'])->name('executions.store');
    Route::delete('executions/{execution}', [ExecutionController::class, 'destroy'])->name('executions.destroy');
    Route::put('executions/{execution}/notes', [ExecutionController::class, 'updateNotes'])->name('executions.notes');
    Route::post('executions/{execution}/issues', [ExecutionIssueController::class, 'store'])->name('execution-issues.store');
    Route::post('executions/{execution}/issues/create', [ExecutionIssueFromFailureController::class, 'store'])->name('execution-issues.create');
    Route::delete('execution-issues/{executionIssue}', [ExecutionIssueController::class, 'destroy'])->name('execution-issues.destroy');

    Route::post('plans/{testPlan}/assignments', [TesterAssignmentController::class, 'store'])->name('tester-assignments.store');
    Route::post('plans/{testPlan}/assignments/copy', [TesterAssignmentController::class, 'copy'])->name('tester-assignments.copy');
    Route::put('tester-assignments/{testerAssignment}', [TesterAssignmentController::class, 'update'])->name('tester-assignments.update');
    Route::delete('tester-assignments/{testerAssignment}', [TesterAssignmentController::class, 'destroy'])->name('tester-assignments.destroy');
});
