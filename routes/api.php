<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\Admin\AdminDeliverableController;
use App\Http\Controllers\Api\V1\AdminRoleController;
use App\Http\Controllers\Api\V1\AdminStatsController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\ChangeRequestController;
use App\Http\Controllers\Api\V1\CodeReviewController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EnumController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\HandoverController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InnovationLogController;
use App\Http\Controllers\Api\V1\MilestoneController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PerformanceEvaluationController;
use App\Http\Controllers\Api\V1\ProposalController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\SalesAnalyticsController;
use App\Http\Controllers\Api\V1\SalesDashboardController;
use App\Http\Controllers\Api\V1\TemplateController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Broadcasting Auth Route (for WebSocket authentication)
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::prefix('v1')->group(function () {
    // Health check endpoint
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // Sales & Pricing Engine Routes
    // All routes have auth:sanctum and locale middleware applied in controllers
    // Lead Management Routes → Moved to app/Modules/Lead/Routes/api.php
    // Contract Management Routes → Moved to app/Modules/Contract/Routes/api.php

    // Activity Management Routes
    Route::apiResource('activities', ActivityController::class);
    Route::post('activities/{activity}/complete', [ActivityController::class, 'complete'])->name('activities.complete');
    Route::post('activities/{activity}/cancel', [ActivityController::class, 'cancel'])->name('activities.cancel');

    // Meeting Management Routes
    Route::get('meetings', [ActivityController::class, 'meetings'])->name('meetings.index');
    Route::post('meetings/{meeting}/complete', [ActivityController::class, 'completeMeeting'])->name('meetings.complete');
    Route::post('meetings/{meeting}/cancel', [ActivityController::class, 'cancelMeeting'])->name('meetings.cancel');

    // Schedule Routes
    Route::get('schedule/today', [ActivityController::class, 'todaySchedule'])->name('schedule.today');
    Route::get('schedule/upcoming', [ActivityController::class, 'upcomingSchedule'])->name('schedule.upcoming');

    // Audit Trail Routes → Moved to app/Modules/Lead/Routes/api.php

    // Proposal Management Routes (Technical Proposals)
    Route::apiResource('proposals', ProposalController::class);
    Route::post('proposals/fast-track', [ProposalController::class, 'fastTrack'])->name('proposals.fast-track');
    Route::post('proposals/{proposal}/upload', [ProposalController::class, 'uploadFile'])->name('proposals.upload');
    Route::post('proposals/{proposal}/send', [ProposalController::class, 'send'])->name('proposals.send');
    Route::post('proposals/{proposal}/approve', [ProposalController::class, 'approve'])->name('proposals.approve');
    Route::post('proposals/{proposal}/reject', [ProposalController::class, 'reject'])->name('proposals.reject');
    Route::get('proposals-expiring', [ProposalController::class, 'expiring'])->name('proposals.expiring');

    // Quotation Management Routes (Price Quotes)
    Route::apiResource('quotations', QuotationController::class);
    Route::post('quotations/calculate', [QuotationController::class, 'calculate'])->name('quotations.calculate');
    Route::post('quotations/validate-payment-plan', [QuotationController::class, 'validatePaymentPlan'])->name('quotations.validate-payment-plan');
    Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
    Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.update-status');
    Route::get('quotations-expiring', [QuotationController::class, 'expiring'])->name('quotations.expiring');
    Route::get('quotations-overdue', [QuotationController::class, 'overdue'])->name('quotations.overdue');

    // Contract Management Routes → Moved to app/Modules/Contract/Routes/api.php

    // Change Request Routes
    Route::get('projects/{project}/change-requests', [ChangeRequestController::class, 'index'])->name('change-requests.index');
    Route::post('projects/{project}/change-requests', [ChangeRequestController::class, 'store'])->name('change-requests.store');
    Route::get('projects/{project}/change-requests/summary', [ChangeRequestController::class, 'summary'])->name('change-requests.summary');
    Route::get('change-requests/{changeRequest}', [ChangeRequestController::class, 'show'])->name('change-requests.show');
    Route::put('change-requests/{changeRequest}', [ChangeRequestController::class, 'update'])->name('change-requests.update');
    Route::post('change-requests/{changeRequest}/submit', [ChangeRequestController::class, 'submit'])->name('change-requests.submit');
    Route::post('change-requests/{changeRequest}/approve', [ChangeRequestController::class, 'approve'])->name('change-requests.approve');
    Route::post('change-requests/{changeRequest}/reject', [ChangeRequestController::class, 'reject'])->name('change-requests.reject');
    Route::post('change-requests/{changeRequest}/implement', [ChangeRequestController::class, 'implement'])->name('change-requests.implement');

    // Project Handover Routes
    Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
    Route::get('handovers/pending', [HandoverController::class, 'pending'])->name('handovers.pending');
    Route::post('projects/{project}/handover', [HandoverController::class, 'store'])->name('handovers.store');
    Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
    Route::put('handovers/{handover}', [HandoverController::class, 'update'])->name('handovers.update');
    Route::post('handovers/{handover}/complete', [HandoverController::class, 'complete'])->name('handovers.complete');
    Route::post('handovers/{handover}/dispute', [HandoverController::class, 'dispute'])->name('handovers.dispute');

    // Template Routes
    Route::prefix('templates')->group(function () {
        Route::get('proposals', [TemplateController::class, 'proposalTemplates'])->name('templates.proposals.index');
        Route::post('proposals', [TemplateController::class, 'storeProposalTemplate'])->name('templates.proposals.store');
        Route::put('proposals/{template}', [TemplateController::class, 'updateProposalTemplate'])->name('templates.proposals.update');
        Route::delete('proposals/{template}', [TemplateController::class, 'destroyProposalTemplate'])->name('templates.proposals.destroy');
        Route::post('proposals/{template}/generate', [TemplateController::class, 'generateProposal'])->name('templates.proposals.generate');

        Route::get('contracts', [TemplateController::class, 'contractTemplates'])->name('templates.contracts.index');
        Route::post('contracts', [TemplateController::class, 'storeContractTemplate'])->name('templates.contracts.store');
        Route::put('contracts/{template}', [TemplateController::class, 'updateContractTemplate'])->name('templates.contracts.update');
        Route::delete('contracts/{template}', [TemplateController::class, 'destroyContractTemplate'])->name('templates.contracts.destroy');
        Route::post('contracts/{template}/generate', [TemplateController::class, 'generateContract'])->name('templates.contracts.generate');
    });

    // Sales Analytics Routes
    Route::prefix('analytics')->group(function () {
        Route::get('sales/performance', [SalesAnalyticsController::class, 'performance'])->name('analytics.sales.performance');
        Route::get('sales/sources', [SalesAnalyticsController::class, 'sources'])->name('analytics.sales.sources');
        Route::get('sales/pipeline', [SalesAnalyticsController::class, 'pipeline'])->name('analytics.sales.pipeline');
        Route::get('revenue/forecast', [SalesAnalyticsController::class, 'forecast'])->name('analytics.revenue.forecast');
        Route::get('quotations/stats', [SalesAnalyticsController::class, 'quotationStats'])->name('analytics.quotations.stats');
        Route::get('projects/types', [SalesAnalyticsController::class, 'projectTypes'])->name('analytics.projects.types');
    });

    // Executive Analytics Routes (Admin/Manager Only)
    Route::prefix('analytics')->middleware(['auth:sanctum', 'locale'])->group(function () {
        Route::get('sales-performance', [AnalyticsController::class, 'salesPerformance'])->name('analytics.sales-performance');
        Route::get('rejection-reasons', [AnalyticsController::class, 'rejectionReasons'])->name('analytics.rejection-reasons');
        Route::get('financial-forecast', [AnalyticsController::class, 'financialForecast'])->name('analytics.financial-forecast');
        Route::get('follow-up-radar', [AnalyticsController::class, 'followUpRadar'])->name('analytics.follow-up-radar');
        Route::get('dashboard-summary', [AnalyticsController::class, 'dashboardSummary'])->name('analytics.dashboard-summary');
        Route::post('clear-cache', [AnalyticsController::class, 'clearCache'])->name('analytics.clear-cache');
    });

    // Milestone Management Routes
    Route::prefix('milestones')->middleware(['auth:sanctum', 'locale'])->group(function () {
        Route::get('{id}', [MilestoneController::class, 'show'])->name('milestones.show');
        Route::post('{id}/create-invoice', [MilestoneController::class, 'createInvoice'])->name('milestones.create-invoice');
        Route::post('{id}/link-invoice', [MilestoneController::class, 'linkInvoice'])->name('milestones.link-invoice');
        Route::patch('{id}/mark-paid', [MilestoneController::class, 'markPaid'])->name('milestones.mark-paid');
    });

    // Dashboard Routes
    Route::get('dashboard/overview', [DashboardController::class, 'overview'])->middleware(['auth:sanctum', 'locale'])->name('dashboard.overview');

    // Notification Routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    });

    // Admin Routes
    // All routes have auth:sanctum and locale middleware applied in controllers
    Route::prefix('admin')->group(function () {
        // Admin Statistics Routes
        Route::prefix('stats')->group(function () {
            Route::get('overview', [AdminStatsController::class, 'overview'])->name('admin.stats.overview');
            Route::get('profitability', [AdminStatsController::class, 'profitability'])->name('admin.stats.profitability');
            Route::get('quality', [AdminStatsController::class, 'quality'])->name('admin.stats.quality');
            Route::get('user-performance', [AdminStatsController::class, 'userPerformance'])->name('admin.stats.user-performance');
            Route::get('attendance', [AdminStatsController::class, 'attendance'])->name('admin.stats.attendance');
            // More specific route first
            Route::get('employee-review/{userId}/export', [AdminStatsController::class, 'exportEmployeeReview'])->name('admin.stats.employee-review.export');
            Route::get('employee-review/{userId}', [AdminStatsController::class, 'employeeReview'])->name('admin.stats.employee-review');
        });

        // Admin Role Management Routes
        Route::apiResource('roles', AdminRoleController::class);
        Route::get('permissions', [AdminRoleController::class, 'permissions'])->name('admin.permissions');

        // Admin Audit Log Routes
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
        Route::get('audit-logs/action-types', [AuditLogController::class, 'actionTypes'])->name('admin.audit-logs.action-types');
        Route::get('audit-logs/stats', [AuditLogController::class, 'stats'])->name('admin.audit-logs.stats');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');

        // Admin Deliverable Management Routes (Full Control)
        Route::prefix('deliverables')->group(function () {
            Route::put('{id}', [AdminDeliverableController::class, 'update'])->name('admin.deliverables.update');
            Route::delete('{id}', [AdminDeliverableController::class, 'destroy'])->name('admin.deliverables.destroy');
            Route::patch('{id}/status', [AdminDeliverableController::class, 'changeStatus'])->name('admin.deliverables.change-status');
            Route::patch('{id}/reset', [AdminDeliverableController::class, 'reset'])->name('admin.deliverables.reset');
            Route::post('{id}/force-approve', [AdminDeliverableController::class, 'forceApprove'])->name('admin.deliverables.force-approve');
            Route::post('{id}/force-reject', [AdminDeliverableController::class, 'forceReject'])->name('admin.deliverables.force-reject');
        });
    });

    // Performance Evaluation Routes
    Route::prefix('performance')->group(function () {
        // Evaluation Periods
        Route::get('periods', [PerformanceEvaluationController::class, 'periods'])->name('performance.periods.index');
        Route::post('periods', [PerformanceEvaluationController::class, 'createPeriod'])->name('performance.periods.store');
        Route::post('periods/{periodId}/activate', [PerformanceEvaluationController::class, 'activatePeriod'])->name('performance.periods.activate');
        Route::get('periods/{periodId}/bonuses', [PerformanceEvaluationController::class, 'periodBonuses'])->name('performance.periods.bonuses');

        // Evaluations
        Route::get('evaluations', [PerformanceEvaluationController::class, 'index'])->name('performance.evaluations.index');
        Route::get('evaluations/{id}', [PerformanceEvaluationController::class, 'show'])->name('performance.evaluations.show');
        Route::put('evaluations/{id}', [PerformanceEvaluationController::class, 'update'])->name('performance.evaluations.update');
        Route::post('evaluations/{id}/calculate', [PerformanceEvaluationController::class, 'calculate'])->name('performance.evaluations.calculate');
        Route::post('evaluations/{id}/approve', [PerformanceEvaluationController::class, 'approve'])->name('performance.evaluations.approve');
        Route::get('my-evaluations', [PerformanceEvaluationController::class, 'myEvaluations'])->name('performance.my-evaluations');
    });

    // Code Review Routes
    Route::prefix('code-reviews')->group(function () {
        Route::get('/', [CodeReviewController::class, 'index'])->name('code-reviews.index');
        Route::post('/', [CodeReviewController::class, 'store'])->name('code-reviews.store');
        Route::get('/my-pending', [CodeReviewController::class, 'myPending'])->name('code-reviews.my-pending');
        Route::get('/my-stats', [CodeReviewController::class, 'myStats'])->name('code-reviews.my-stats');
        Route::get('/{id}', [CodeReviewController::class, 'show'])->name('code-reviews.show');
        Route::put('/{id}', [CodeReviewController::class, 'update'])->name('code-reviews.update');
        Route::post('/{id}/approve', [CodeReviewController::class, 'approve'])->name('code-reviews.approve');
        Route::post('/{id}/request-revision', [CodeReviewController::class, 'requestRevision'])->name('code-reviews.request-revision');
        Route::post('/{id}/reject', [CodeReviewController::class, 'reject'])->name('code-reviews.reject');
    });

    // Innovation Log Routes
    Route::prefix('innovation-logs')->group(function () {
        Route::get('/', [InnovationLogController::class, 'index'])->name('innovation-logs.index');
        Route::post('/', [InnovationLogController::class, 'store'])->name('innovation-logs.store');
        Route::get('/pending', [InnovationLogController::class, 'pending'])->name('innovation-logs.pending');
        Route::get('/my-summary', [InnovationLogController::class, 'mySummary'])->name('innovation-logs.my-summary');
        Route::get('/{id}', [InnovationLogController::class, 'show'])->name('innovation-logs.show');
        Route::post('/{id}/verify', [InnovationLogController::class, 'verify'])->name('innovation-logs.verify');
        Route::post('/{id}/reject', [InnovationLogController::class, 'reject'])->name('innovation-logs.reject');
        Route::delete('/{id}', [InnovationLogController::class, 'destroy'])->name('innovation-logs.destroy');
    });

    // Enum Routes
    Route::prefix('enums')->group(function () {
        Route::get('user-specializations', [EnumController::class, 'getUserSpecializations'])->name('enums.user-specializations');
        Route::get('task-categories', [EnumController::class, 'getTaskCategories'])->name('enums.task-categories');
        Route::get('compatible-task-categories/{specialization}', [EnumController::class, 'getCompatibleTaskCategories'])->name('enums.compatible-task-categories');
    });

    // Sales Dashboard Routes (Manager Dashboard)
    Route::prefix('sales-dashboard')->group(function () {
        Route::get('summary', [SalesDashboardController::class, 'summary'])->name('sales-dashboard.summary');
        Route::get('pipeline', [SalesDashboardController::class, 'pipeline'])->name('sales-dashboard.pipeline');
        Route::get('team-performance', [SalesDashboardController::class, 'teamPerformance'])->name('sales-dashboard.team-performance');
        Route::get('bottlenecks', [SalesDashboardController::class, 'bottlenecks'])->name('sales-dashboard.bottlenecks');
        Route::get('alerts', [SalesDashboardController::class, 'alerts'])->name('sales-dashboard.alerts');
    });

    // Global Search Route
    Route::get('search', [GlobalSearchController::class, 'search'])->name('search');

    // Export Routes
    Route::prefix('export')->group(function () {
        Route::get('leads', [ExportController::class, 'exportLeads'])->name('export.leads');
        Route::get('proposals', [ExportController::class, 'exportProposals'])->name('export.proposals');
        Route::get('quotations', [ExportController::class, 'exportQuotations'])->name('export.quotations');
        Route::get('sales-report', [ExportController::class, 'salesReport'])->name('export.sales-report');
    });

    // HR Deductions & Rewards Routes
    Route::prefix('hr')->middleware(['auth:sanctum', 'locale'])->group(function () {
        // Deduction Types Routes
        Route::apiResource('deduction-types', \App\Http\Controllers\Api\V1\HR\DeductionTypeController::class);

        // Reward Types Routes
        Route::apiResource('reward-types', \App\Http\Controllers\Api\V1\HR\RewardTypeController::class);
        Route::post('reward-types/{reward_type}/calculate', [\App\Http\Controllers\Api\V1\HR\RewardTypeController::class, 'calculate'])->name('hr.reward-types.calculate');

        // Employee Deductions Routes
        Route::apiResource('employee-deductions', \App\Http\Controllers\Api\V1\HR\EmployeeDeductionController::class);
        Route::post('employee-deductions/{employeeDeduction}/approve', [\App\Http\Controllers\Api\V1\HR\EmployeeDeductionController::class, 'approve'])->name('hr.employee-deductions.approve');
        Route::post('employee-deductions/{employeeDeduction}/reject', [\App\Http\Controllers\Api\V1\HR\EmployeeDeductionController::class, 'reject'])->name('hr.employee-deductions.reject');
        Route::get('employee-deductions-summary', [\App\Http\Controllers\Api\V1\HR\EmployeeDeductionController::class, 'summary'])->name('hr.employee-deductions.summary');
        Route::post('employee-deductions/bulk-from-attendance', [\App\Http\Controllers\Api\V1\HR\EmployeeDeductionController::class, 'bulkFromAttendance'])->name('hr.employee-deductions.bulk-from-attendance');

        // Employee Rewards Routes
        Route::apiResource('employee-rewards', \App\Http\Controllers\Api\V1\HR\EmployeeRewardController::class);
        Route::post('employee-rewards/{employeeReward}/approve', [\App\Http\Controllers\Api\V1\HR\EmployeeRewardController::class, 'approve'])->name('hr.employee-rewards.approve');
        Route::post('employee-rewards/{employeeReward}/reject', [\App\Http\Controllers\Api\V1\HR\EmployeeRewardController::class, 'reject'])->name('hr.employee-rewards.reject');
        Route::post('employee-rewards/{employeeReward}/mark-paid', [\App\Http\Controllers\Api\V1\HR\EmployeeRewardController::class, 'markAsPaid'])->name('hr.employee-rewards.mark-paid');
        Route::get('employee-rewards-summary', [\App\Http\Controllers\Api\V1\HR\EmployeeRewardController::class, 'summary'])->name('hr.employee-rewards.summary');
        Route::post('employee-rewards/from-points', [\App\Http\Controllers\Api\V1\HR\EmployeeRewardController::class, 'fromPoints'])->name('hr.employee-rewards.from-points');
        Route::post('employee-rewards/bulk-from-evaluation', [\App\Http\Controllers\Api\V1\HR\EmployeeRewardController::class, 'bulkFromEvaluation'])->name('hr.employee-rewards.bulk-from-evaluation');

        // Salary Adjustments Routes
        Route::get('salary-adjustments', [\App\Http\Controllers\Api\V1\HR\SalaryAdjustmentController::class, 'index'])->name('hr.salary-adjustments.index');
        Route::get('salary-adjustments/{salaryAdjustment}', [\App\Http\Controllers\Api\V1\HR\SalaryAdjustmentController::class, 'show'])->name('hr.salary-adjustments.show');
        Route::get('salary-adjustments-payroll-summary', [\App\Http\Controllers\Api\V1\HR\SalaryAdjustmentController::class, 'payrollSummary'])->name('hr.salary-adjustments.payroll-summary');
    });
});
