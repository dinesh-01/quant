<?php

use App\Http\Controllers\RoleAssignments\PlanMemberController;
use App\Http\Controllers\RoleAssignments\ProjectMemberController;
use Illuminate\Support\Facades\Route;

/*
 * Who holds a role for a project or a plan.
 *
 * The member is identified by its own binding on delete rather than being
 * scoped to the parent, because a user is not a child of a project — the
 * assignment is, and the pair of ids is what identifies it.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('projects/{testProject}/members', [ProjectMemberController::class, 'index'])
        ->name('projects.members.index');
    Route::post('projects/{testProject}/members', [ProjectMemberController::class, 'store'])
        ->name('projects.members.store');
    Route::delete('projects/{testProject}/members/{user}', [ProjectMemberController::class, 'destroy'])
        ->name('projects.members.destroy');

    Route::get('plans/{testPlan}/members', [PlanMemberController::class, 'index'])
        ->name('plans.members.index');
    Route::post('plans/{testPlan}/members', [PlanMemberController::class, 'store'])
        ->name('plans.members.store');
    Route::delete('plans/{testPlan}/members/{user}', [PlanMemberController::class, 'destroy'])
        ->name('plans.members.destroy');
});
