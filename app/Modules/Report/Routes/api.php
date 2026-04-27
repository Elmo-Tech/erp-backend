<?php

use App\Modules\Report\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('admin/stats')->group(function () {
        Route::get('overview', [ReportController::class, 'adminOverview'])->name('admin.stats.overview');
        Route::get('profitability', [ReportController::class, 'profitability'])->name('admin.stats.profitability');
        Route::get('quality', [ReportController::class, 'quality'])->name('admin.stats.quality');
        Route::get('user-performance', [ReportController::class, 'userPerformance'])->name('admin.stats.user-performance');
        Route::get('attendance', [ReportController::class, 'attendance'])->name('admin.stats.attendance');
        Route::get('employee-review/{userId}/export', [ReportController::class, 'exportEmployeeReview'])->name('admin.stats.employee-review.export');
        Route::get('employee-review/{userId}', [ReportController::class, 'employeeReview'])->name('admin.stats.employee-review');
    });

    Route::prefix('reports')->group(function () {
        Route::get('project-progress', [ReportController::class, 'projectProgress'])->name('reports.project-progress');
        Route::get('sales-report', [ReportController::class, 'salesReport'])->name('reports.sales-report');
    });
});
