<?php

use App\Http\Controllers\CustomFields\CustomFieldController;
use App\Http\Controllers\CustomFields\ProjectCustomFieldController;
use Illuminate\Support\Facades\Route;

/*
 * The catalogue is not nested under a project, because a definition is not
 * owned by one: it is application-wide, and editing it reaches every project
 * that has it enabled. Which projects those are is the second group below,
 * which is nested, because that decision belongs to the project.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('custom-fields', [CustomFieldController::class, 'index'])->name('custom-fields.index');
    Route::get('custom-fields/create', [CustomFieldController::class, 'create'])->name('custom-fields.create');
    Route::post('custom-fields', [CustomFieldController::class, 'store'])->name('custom-fields.store');
    Route::get('custom-fields/{customField}/edit', [CustomFieldController::class, 'edit'])->name('custom-fields.edit');
    Route::put('custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
    Route::delete('custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('custom-fields.destroy');

    Route::get('projects/{testProject}/custom-fields', [ProjectCustomFieldController::class, 'index'])
        ->name('projects.custom-fields.index');
    Route::put('projects/{testProject}/custom-fields', [ProjectCustomFieldController::class, 'update'])
        ->name('projects.custom-fields.update');
});
