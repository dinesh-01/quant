<?php

use App\Http\Controllers\Keywords\KeywordAssignmentController;
use App\Http\Controllers\Keywords\KeywordController;
use Illuminate\Support\Facades\Route;

/*
 * The catalogue is nested under its project, because a keyword name only means
 * something inside one and the list has to be reached before any keyword
 * exists. Editing binds the keyword alone: it already knows its project, and a
 * nested URL would let the two disagree.
 *
 * Assignment is keyed by the thing being tagged rather than by the keyword,
 * which is the direction every screen works in — a case is open and keywords
 * are being chosen for it, never the reverse.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('projects/{testProject}/keywords', [KeywordController::class, 'index'])->name('keywords.index');
    Route::get('projects/{testProject}/keywords/create', [KeywordController::class, 'create'])->name('keywords.create');
    Route::post('projects/{testProject}/keywords', [KeywordController::class, 'store'])->name('keywords.store');

    Route::get('keywords/{keyword}/edit', [KeywordController::class, 'edit'])->name('keywords.edit');
    Route::put('keywords/{keyword}', [KeywordController::class, 'update'])->name('keywords.update');
    Route::delete('keywords/{keyword}', [KeywordController::class, 'destroy'])->name('keywords.destroy');

    Route::put('test-cases/{testCase}/keywords', [KeywordAssignmentController::class, 'updateForTestCase'])
        ->name('test-cases.keywords.update');

    Route::post('test-suites/{testSuite}/keywords', [KeywordAssignmentController::class, 'storeForTestSuite'])
        ->name('test-suites.keywords.store');
});
