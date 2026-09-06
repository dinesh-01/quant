<?php

use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

/*
 * The user directory. There is no destroy route on purpose — accounts are
 * deactivated, never deleted, so the history later phases attribute to them
 * survives. See `App\Actions\Users\UpdateUser`.
 *
 * `create` is declared before the bound routes so it is not read as a user id.
 *
 * The password route is separate from `update` because it needs a stricter
 * ability than the rest of that form. See `App\Actions\Users\SetUserPassword`.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('users/{user}/password', [UserController::class, 'updatePassword'])->name('users.password.update');
    Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordResetLink'])
        ->middleware('throttle:6,1')
        ->name('users.password-reset.store');
});
