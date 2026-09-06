<?php

use App\Http\Controllers\Audit\EventLogController;
use Illuminate\Support\Facades\Route;

/**
 * The event log is read-only apart from pruning, so there is no create, edit
 * or update route — an audit record that can be changed is not an audit
 * record. `destroy` trims by retention period rather than removing one row,
 * which is deliberately not offered.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('events', [EventLogController::class, 'index'])->name('events.index');
    Route::delete('events', [EventLogController::class, 'destroy'])->name('events.destroy');
});
