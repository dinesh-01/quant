<?php

use App\Http\Controllers\TestPlans\TestPlanController;
use Illuminate\Support\Facades\Route;

/*
 * Plan management inside a project.
 *
 * The list and create routes are nested under the project because no plan
 * exists yet to identify it. Editing an existing plan binds the plan alone: it
 * already knows its project, and a nested URL would let the two disagree.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('projects/{testProject}/plans', [TestPlanController::class, 'index'])->name('plans.index');
    Route::get('projects/{testProject}/plans/create', [TestPlanController::class, 'create'])->name('plans.create');
    Route::post('projects/{testProject}/plans', [TestPlanController::class, 'store'])->name('plans.store');

    Route::get('plans/{testPlan}/edit', [TestPlanController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{testPlan}', [TestPlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{testPlan}', [TestPlanController::class, 'destroy'])->name('plans.destroy');
});
