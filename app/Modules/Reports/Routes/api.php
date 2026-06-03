<?php

use App\Modules\Reports\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/reports')
    ->middleware(['auth:sanctum', 'locale'])
    ->group(function (): void {
        Route::get('overview', [ReportsController::class, 'overview'])->name('reports.overview');
        Route::get('projects', [ReportsController::class, 'projects'])->name('reports.projects');
        Route::get('team-time', [ReportsController::class, 'teamTime'])->name('reports.team-time');
        Route::get('sales-finance', [ReportsController::class, 'salesFinance'])->name('reports.sales-finance');
        Route::get('export', [ReportsController::class, 'export'])->name('reports.export');
    });
