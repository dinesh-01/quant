<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/attachments.php';
require __DIR__.'/audit.php';
require __DIR__.'/custom-fields.php';
require __DIR__.'/keywords.php';
require __DIR__.'/planning.php';
require __DIR__.'/requirements.php';
require __DIR__.'/platforms.php';
require __DIR__.'/reports.php';
require __DIR__.'/code-trackers.php';
require __DIR__.'/issue-trackers.php';
require __DIR__.'/role-assignments.php';
require __DIR__.'/roles.php';
require __DIR__.'/test-plans.php';
require __DIR__.'/test-projects.php';
require __DIR__.'/test-specification.php';
require __DIR__.'/users.php';
