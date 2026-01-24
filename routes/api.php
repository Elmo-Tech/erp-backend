<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AdminStatsController;
use App\Http\Controllers\Api\V1\AdminRoleController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChangeRequestController;
use App\Http\Controllers\Api\V1\Client\ClientInvoiceController;
use App\Http\Controllers\Api\V1\Client\ClientProjectController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\HandoverController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\LeadHistoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectFileController;
use App\Http\Controllers\Api\V1\ProjectHistoryController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\ProjectWikiController;
use App\Http\Controllers\Api\V1\ProposalController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\SalesAnalyticsController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskDeliverableController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\TimeEntryController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\PerformanceEvaluationController;
use App\Http\Controllers\Api\V1\CodeReviewController;
use App\Http\Controllers\Api\V1\MilestoneController;
use App\Http\Controllers\Api\V1\InnovationLogController;
use App\Http\Controllers\Api\V1\EnumController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\SalesDashboardController;
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

    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('profile', [AuthController::class, 'profile'])->name('auth.profile');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
        Route::post('profile/avatar', [AuthController::class, 'updateAvatar'])->name('auth.profile.avatar');
    });

    // Sales & Pricing Engine Routes
    // All routes have auth:sanctum and locale middleware applied in controllers
    
    // Lead Management Routes
    Route::apiResource('leads', LeadController::class);
    Route::get('leads-kanban', [LeadController::class, 'kanban'])->name('leads.kanban');
    Route::get('leads-follow-ups', [LeadController::class, 'followUps'])->name('leads.follow-ups');
    Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.update-status');
    Route::get('leads/{lead}/activities', [LeadController::class, 'activities'])->name('leads.activities');
    Route::post('leads/{lead}/activities', [LeadController::class, 'addActivity'])->name('leads.add-activity');
    Route::get('leads/{lead}/meetings', [LeadController::class, 'meetings'])->name('leads.meetings');
    Route::post('leads/{lead}/meetings', [LeadController::class, 'scheduleMeeting'])->name('leads.schedule-meeting');
    Route::put('leads/{lead}/meetings/{meeting}', [LeadController::class, 'updateMeeting'])->name('leads.update-meeting');
    
    // Lead Conversion Routes
    Route::get('leads/{lead}/can-convert', [\App\Http\Controllers\Api\V1\LeadConversionController::class, 'canConvert'])->name('leads.can-convert');
    Route::post('leads/{lead}/convert', [\App\Http\Controllers\Api\V1\LeadConversionController::class, 'convert'])->name('leads.convert');
    
    // Activity Management Routes
    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::post('activities/{activity}/complete', [ActivityController::class, 'complete'])->name('activities.complete');
    Route::post('activities/{activity}/cancel', [ActivityController::class, 'cancel'])->name('activities.cancel');
    
    // Meeting Management Routes
    Route::get('meetings', [ActivityController::class, 'meetings'])->name('meetings.index');
    Route::post('meetings/{meeting}/complete', [ActivityController::class, 'completeMeeting'])->name('meetings.complete');
    Route::post('meetings/{meeting}/cancel', [ActivityController::class, 'cancelMeeting'])->name('meetings.cancel');
    
    // Schedule Routes
    Route::get('schedule/today', [ActivityController::class, 'todaySchedule'])->name('schedule.today');
    Route::get('schedule/upcoming', [ActivityController::class, 'upcomingSchedule'])->name('schedule.upcoming');
    
    // Audit Trail Routes
    Route::get('leads/{lead}/history', [LeadHistoryController::class, 'leadHistory'])->name('leads.history');
    Route::get('leads/{lead}/full-history', [LeadHistoryController::class, 'leadFullHistory'])->name('leads.full-history');
    Route::get('leads/{lead}/history-stats', [LeadHistoryController::class, 'leadHistoryStats'])->name('leads.history-stats');
    Route::get('proposals/{proposal}/history', [LeadHistoryController::class, 'proposalHistory'])->name('proposals.history');
    Route::get('quotations/{quotation}/history', [LeadHistoryController::class, 'quotationHistory'])->name('quotations.history');
    Route::get('audit/recent', [LeadHistoryController::class, 'recentActivity'])->name('audit.recent');
    Route::get('audit/user/{userId}', [LeadHistoryController::class, 'userActivity'])->name('audit.user');
    
    // Client Management Routes
    Route::apiResource('clients', ClientController::class);
    
    // Client Portal Access Routes
    Route::post('clients/{client}/portal-access', [\App\Http\Controllers\Api\V1\ClientPortalAccessController::class, 'create'])->name('clients.portal-access.create');
    Route::delete('clients/{client}/portal-access', [\App\Http\Controllers\Api\V1\ClientPortalAccessController::class, 'revoke'])->name('clients.portal-access.revoke');
    Route::post('clients/{client}/portal-access/reset-password', [\App\Http\Controllers\Api\V1\ClientPortalAccessController::class, 'resetPassword'])->name('clients.portal-access.reset-password');
    
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
    Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
    Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.update-status');
    Route::get('quotations-expiring', [QuotationController::class, 'expiring'])->name('quotations.expiring');
    Route::get('quotations-overdue', [QuotationController::class, 'overdue'])->name('quotations.overdue');

    // Contract Management Routes
    Route::apiResource('contracts', ContractController::class);
    Route::post('contracts/from-quotation/{quotation}', [ContractController::class, 'createFromQuotation'])->name('contracts.from-quotation');
    Route::post('contracts/{contract}/sign', [ContractController::class, 'sign'])->name('contracts.sign');
    Route::post('contracts/{contract}/activate', [ContractController::class, 'activate'])->name('contracts.activate');
    Route::patch('contracts/{contract}/status', [ContractController::class, 'updateStatus'])->name('contracts.update-status');
    Route::get('contracts/milestones/due', [ContractController::class, 'dueMilestones'])->name('contracts.milestones.due');
    Route::patch('contracts/milestones/{milestone}/paid', [ContractController::class, 'markMilestonePaid'])->name('contracts.milestones.paid');

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

    // Time Entry Routes
    Route::post('time-entries/start', [TimeEntryController::class, 'start'])->name('time-entries.start');
    Route::post('time-entries/stop', [TimeEntryController::class, 'stop'])->name('time-entries.stop');
    Route::get('time-entries', [TimeEntryController::class, 'index'])->name('time-entries.index');
    Route::get('time-entries/active', [TimeEntryController::class, 'active'])->name('time-entries.active');
    Route::get('time-entries/active-timers', [TimeEntryController::class, 'activeTimers'])->name('time-entries.active-timers');
    Route::post('time-entries/{id}/stop-admin', [TimeEntryController::class, 'stopTimerAdmin'])->name('time-entries.stop-admin');
    Route::put('time-entries/{id}', [TimeEntryController::class, 'update'])->name('time-entries.update');
    Route::get('time-entries/all', [TimeEntryController::class, 'all'])->name('time-entries.all');

    // Attendance Routes
    // All routes have auth:sanctum and locale middleware applied in controller
    Route::get('attendance/today', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::get('attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
    Route::get('attendance/summary', [AttendanceController::class, 'summary'])->name('attendance.summary');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::get('attendance/all', [AttendanceController::class, 'all'])->name('attendance.all');
    Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('attendance/{id}', [AttendanceController::class, 'update'])->name('attendance.update');

    // Employee Management Routes
    Route::apiResource('employees', EmployeeController::class)->except(['destroy']);
    Route::get('employees/{id}/performance', [EmployeeController::class, 'performance'])->name('employees.performance');
    Route::post('employees/{id}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
    Route::post('employees/{id}/activate', [EmployeeController::class, 'activate'])->name('employees.activate');

    // Project Management Routes
    // All routes have auth:sanctum and locale middleware applied in controllers
    
    // Project CRUD Routes
    Route::apiResource('projects', ProjectController::class);
    
    // Project Status Update Route (flexible status transitions)
    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.update-status');
    
    // Project Conversion Route (convert quotation to project)
    Route::post('projects/convert', [ProjectController::class, 'convert'])->name('projects.convert');
    
    // Direct Project Creation Route (create project for client without quotation)
    Route::post('projects/direct', [ProjectController::class, 'storeDirect'])->name('projects.store-direct');
    
    // Project UI Approval Route (manual approval of UI/UX deliverables)
    Route::post('projects/{project}/approve-ui', [ProjectController::class, 'approveUI'])->name('projects.approve-ui');
    
    // Project History Route (audit trail)
    Route::get('projects/{project}/history', [ProjectHistoryController::class, 'index'])->name('projects.history');
    
    // Project Wiki Routes (nested resource)
    Route::apiResource('projects.wikis', ProjectWikiController::class);
    
    // Project Files Routes
    Route::apiResource('projects.files', ProjectFileController::class);
    
    // Project Members Routes
    Route::apiResource('projects.members', ProjectMemberController::class)->except(['show']);

    // Task Management Routes
    // All routes have auth:sanctum and locale middleware applied in controller
    
    // My Assigned Tasks Route (tasks where user is team member) - MUST be before apiResource
    Route::get('tasks/my-assigned', [TaskController::class, 'myAssignedTasks'])->name('tasks.my-assigned');
    
    // Task CRUD Routes
    Route::apiResource('tasks', TaskController::class);
    
    // Task Status Update Route (flexible status transitions)
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.update-status');
    
    // Task Children Route (for lazy loading hierarchy)
    Route::get('tasks/{task}/children', [TaskController::class, 'children'])->name('tasks.children');
    
    // Task Assignees Routes
    Route::post('tasks/{task}/assignees', [TaskController::class, 'addAssignee'])->name('tasks.assignees.add');
    Route::delete('tasks/{task}/assignees/{userId}', [TaskController::class, 'removeAssignee'])->name('tasks.assignees.remove');
    
    // Task Test Scenarios Routes
    Route::get('tasks/{task}/test-scenarios', [TaskController::class, 'testScenarios'])->name('tasks.test-scenarios.index');
    Route::post('tasks/{task}/test-scenarios', [TaskController::class, 'addTestScenario'])->name('tasks.test-scenarios.add');
    Route::post('test-scenarios/{scenario}/complete', [TaskController::class, 'completeTestScenario'])->name('test-scenarios.complete');
    Route::post('test-scenarios/{scenario}/verify', [TaskController::class, 'verifyTestScenario'])->name('test-scenarios.verify');
    
    // Task Activities & Comments Routes
    Route::get('tasks/{task}/activities', [TaskController::class, 'activities'])->name('tasks.activities');
    Route::post('tasks/{task}/comments', [TaskController::class, 'addComment'])->name('tasks.comments.add');
    
    // Tags Route
    Route::get('tags', [TaskController::class, 'tags'])->name('tags.index');
    
    // Project Tasks Route (get tasks for a specific project with hierarchy)
    Route::get('projects/{projectId}/tasks', [TaskController::class, 'projectTasks'])->name('projects.tasks');
    
    // Project Points Summary Route
    Route::get('projects/{projectId}/points-summary', [TaskController::class, 'pointsSummary'])->name('projects.points-summary');

    // Task Deliverables Routes
    // All routes have auth:sanctum and locale middleware applied in controller
    Route::get('tasks/{taskId}/deliverables', [TaskDeliverableController::class, 'index'])->name('tasks.deliverables.index');
    Route::post('tasks/{taskId}/deliverables', [TaskDeliverableController::class, 'store'])->name('tasks.deliverables.store');
    Route::put('deliverables/{id}', [TaskDeliverableController::class, 'update'])->name('deliverables.update');
    Route::delete('deliverables/{id}', [TaskDeliverableController::class, 'destroy'])->name('deliverables.destroy');
    Route::post('deliverables/{id}/submit', [TaskDeliverableController::class, 'submit'])->name('deliverables.submit');
    Route::post('deliverables/{id}/start-review', [TaskDeliverableController::class, 'startReview'])->name('deliverables.start-review');
    Route::post('deliverables/{id}/approve', [TaskDeliverableController::class, 'approve'])->name('deliverables.approve');
    Route::post('deliverables/{id}/reject', [TaskDeliverableController::class, 'reject'])->name('deliverables.reject');
    Route::post('tasks/{taskId}/deliverables/reorder', [TaskDeliverableController::class, 'reorder'])->name('tasks.deliverables.reorder');

    // Client Portal Routes
    // All routes have auth:sanctum, locale, and role:client middleware applied in controllers
    // ClientScope automatically filters data to client's own records
    Route::prefix('client')->middleware(['auth:sanctum', 'locale', 'role:client'])->group(function () {
        // Client Project Routes
        Route::get('projects', [ClientProjectController::class, 'index'])->name('client.projects.index');
        Route::get('projects/{project}', [ClientProjectController::class, 'show'])->name('client.projects.show');
        Route::post('projects/{project}/approve-ui', [ClientProjectController::class, 'approveUI'])->name('client.projects.approve-ui');
        
        // Client Invoice Routes
        Route::get('invoices', [ClientInvoiceController::class, 'index'])->name('client.invoices.index');
        Route::get('invoices/{invoice}', [ClientInvoiceController::class, 'show'])->name('client.invoices.show');
        Route::post('invoices/{invoice}/pay', [ClientInvoiceController::class, 'pay'])->name('client.invoices.pay');
    });

    // Admin Invoice Management Routes
    // All routes have auth:sanctum and locale middleware applied at route level
    Route::apiResource('invoices', InvoiceController::class)->middleware(['auth:sanctum', 'locale']);

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

        // Admin User Management Routes (Internal Costs)
        Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::put('users/{id}/internal-cost', [AdminUserController::class, 'updateInternalCost'])->name('admin.users.update-internal-cost');
        Route::put('users/internal-costs', [AdminUserController::class, 'bulkUpdateInternalCosts'])->name('admin.users.bulk-update-internal-costs');

        // User Management Routes (CRUD)
        Route::apiResource('users/manage', UserController::class)->parameters(['manage' => 'user']);
        Route::post('users/manage/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggle-status');
        Route::put('users/manage/{user}/roles', [UserController::class, 'assignRoles'])->name('admin.users.assign-roles');

        // Admin Audit Log Routes
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
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

    // User Points Management Routes
    Route::prefix('user-points')->group(function () {
        Route::get('/transaction-types', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'getTransactionTypes'])->name('user-points.transaction-types');
        Route::get('/', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'index'])->name('user-points.index');
        Route::post('/add', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'addPoints'])->name('user-points.add');
        Route::post('/deduct', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'deductPoints'])->name('user-points.deduct');
        Route::post('/set', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'setPoints'])->name('user-points.set');
        Route::get('/history/{userId}', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'history'])->name('user-points.history');
        Route::get('/unified-history/{userId}', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'getUnifiedHistory'])->name('user-points.unified-history');
        Route::delete('/transaction/{transactionId}', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'deleteTransaction'])->name('user-points.delete-transaction');
        Route::get('/leaderboard', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'leaderboard'])->name('user-points.leaderboard');
        Route::get('/statistics', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'statistics'])->name('user-points.statistics');
        Route::get('/report', [\App\Http\Controllers\Api\V1\UserPointsController::class, 'report'])->name('user-points.report');
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
});
