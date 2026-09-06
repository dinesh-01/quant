<?php

use App\Http\Controllers\Reports\PlanReportsController;
use App\Http\Controllers\Reports\ProjectReportsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('projects/{testProject}/reports', [ProjectReportsController::class, 'index'])
        ->name('reports.project');

    Route::get('plans/{testPlan}/reports', [PlanReportsController::class, 'show'])
        ->name('reports.plan');

    Route::post('plans/{testPlan}/reports/baselines', [PlanReportsController::class, 'storeBaseline'])
        ->name('reports.plan-baselines.store');

    Route::get('plans/{testPlan}/reports/status.csv', [PlanReportsController::class, 'statusCsv'])
        ->name('reports.plan-status');

    Route::get('plans/{testPlan}/reports/status.xlsx', [PlanReportsController::class, 'statusXlsx'])
        ->name('reports.plan-status-xlsx');
});
