<?php

use App\Modules\Report\Controllers\AnalyticsController;
use App\Modules\Report\Controllers\ExportController;
use App\Modules\Report\Controllers\ReportController;
use App\Modules\Report\Controllers\SalesAnalyticsController;
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

    Route::prefix('analytics')->group(function () {
        Route::get('sales/performance', [SalesAnalyticsController::class, 'performance'])->name('analytics.sales.performance');
        Route::get('sales/sources', [SalesAnalyticsController::class, 'sources'])->name('analytics.sales.sources');
        Route::get('sales/pipeline', [SalesAnalyticsController::class, 'pipeline'])->name('analytics.sales.pipeline');
        Route::get('revenue/forecast', [SalesAnalyticsController::class, 'forecast'])->name('analytics.revenue.forecast');
        Route::get('quotations/stats', [SalesAnalyticsController::class, 'quotationStats'])->name('analytics.quotations.stats');
        Route::get('projects/types', [SalesAnalyticsController::class, 'projectTypes'])->name('analytics.projects.types');
    });

    Route::prefix('analytics')->middleware(['auth:sanctum', 'locale'])->group(function () {
        Route::get('sales-performance', [AnalyticsController::class, 'salesPerformance'])->name('analytics.sales-performance');
        Route::get('rejection-reasons', [AnalyticsController::class, 'rejectionReasons'])->name('analytics.rejection-reasons');
        Route::get('financial-forecast', [AnalyticsController::class, 'financialForecast'])->name('analytics.financial-forecast');
        Route::get('follow-up-radar', [AnalyticsController::class, 'followUpRadar'])->name('analytics.follow-up-radar');
        Route::get('dashboard-summary', [AnalyticsController::class, 'dashboardSummary'])->name('analytics.dashboard-summary');
        Route::post('clear-cache', [AnalyticsController::class, 'clearCache'])->name('analytics.clear-cache');
    });

    Route::prefix('export')->group(function () {
        Route::get('leads', [ExportController::class, 'exportLeads'])->name('export.leads');
        Route::get('proposals', [ExportController::class, 'exportProposals'])->name('export.proposals');
        Route::get('quotations', [ExportController::class, 'exportQuotations'])->name('export.quotations');
    });
});
