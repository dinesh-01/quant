<?php

use App\Http\Controllers\Roles\RoleController;
use Illuminate\Support\Facades\Route;

/*
 * Role administration. `create` is declared before the bound routes so it is
 * not read as a role id.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});
