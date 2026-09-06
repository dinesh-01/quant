<?php

use App\Http\Controllers\TestProjects\TestProjectController;
use Illuminate\Support\Facades\Route;

/*
 * Project administration and the project list that every project-scoped area
 * is entered through.
 *
 * `create` is declared before the bound routes so it is not read as a project
 * identifier.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('projects', [TestProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [TestProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [TestProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{testProject}/edit', [TestProjectController::class, 'edit'])->name('projects.edit');
    Route::put('projects/{testProject}', [TestProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{testProject}', [TestProjectController::class, 'destroy'])->name('projects.destroy');
});
