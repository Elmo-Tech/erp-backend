<?php

use App\Modules\Sales\Controllers\GlobalSearchController;
use App\Modules\Sales\Controllers\SalesDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('sales-dashboard')->group(function () {
        Route::get('summary', [SalesDashboardController::class, 'summary'])->name('sales-dashboard.summary');
        Route::get('pipeline', [SalesDashboardController::class, 'pipeline'])->name('sales-dashboard.pipeline');
        Route::get('team-performance', [SalesDashboardController::class, 'teamPerformance'])->name('sales-dashboard.team-performance');
        Route::get('bottlenecks', [SalesDashboardController::class, 'bottlenecks'])->name('sales-dashboard.bottlenecks');
        Route::get('alerts', [SalesDashboardController::class, 'alerts'])->name('sales-dashboard.alerts');
    });

    Route::get('search', [GlobalSearchController::class, 'search'])->name('search');
});
