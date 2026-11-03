<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\Admin\AdminDeliverableController;
use App\Http\Controllers\Api\V1\AdminRoleController;
use App\Http\Controllers\Api\V1\AdminStatsController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChangeRequestController;
use App\Http\Controllers\Api\V1\Client\ClientInvoiceController;
use App\Http\Controllers\Api\V1\Client\ClientProjectController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ClientPortalAccessController;
use App\Http\Controllers\Api\V1\CodeReviewController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceBridgeController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DeviceWebhookController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EnumController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\GoogleDriveController;
use App\Http\Controllers\Api\V1\HandoverController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InnovationLogController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\LeadConversionController;
use App\Http\Controllers\Api\V1\LeadHistoryController;
use App\Http\Controllers\Api\V1\LookupOptionController;
use App\Http\Controllers\Api\V1\MilestoneController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PerformanceEvaluationController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectFileController;
use App\Http\Controllers\Api\V1\ProjectHistoryController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\ProjectPipelineController;
use App\Http\Controllers\Api\V1\ProjectWikiController;
use App\Http\Controllers\Api\V1\ProposalController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\RecurringTaskController;
use App\Http\Controllers\Api\V1\SalesAnalyticsController;
use App\Http\Controllers\Api\V1\SalesDashboardController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskDeliverableController;
use App\Http\Controllers\Api\V1\TaskDependencyController;
use App\Http\Controllers\Api\V1\TaskSupervisionController;
use App\Http\Controllers\Api\V1\TaskTemplateController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\TimeEntryController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserPointsController;
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

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    // Health check endpoint
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // Access control device webhook (push mode).
    Route::post('device-bridge/{id}/events', DeviceBridgeController::class)
        ->middleware('throttle:device-webhook')->name('device-bridge.events');
    // The device authenticates with the per-device token in the URL.
    Route::post('device-events/{token}', [DeviceWebhookController::class, 'handle'])
        ->middleware('throttle:device-webhook')
        ->name('device-events.handle');

    // Authentication Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('notifications/settings', [PushSubscriptionController::class, 'settings']);
        Route::put('notifications/preferences', [PushSubscriptionController::class, 'preferences']);
        Route::post('notifications/push-subscriptions', [PushSubscriptionController::class, 'store']);
        Route::delete('notifications/push-subscriptions', [PushSubscriptionController::class, 'destroy']);
    });
    // Security: Rate limiting applied to prevent brute force attacks
    // Registration is disabled by default - use admin panel to create users
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('auth.login');

        // Registration disabled for security - users created via admin panel
        // To enable registration, uncomment the line below and add throttle middleware
        // Route::post('register', [AuthController::class, 'register'])
        //     ->middleware('throttle:3,1')
        //     ->name('auth.register');

        // Protected auth routes
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('profile', [AuthController::class, 'profile'])->name('auth.profile');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
        Route::post('profile/avatar', [AuthController::class, 'updateAvatar'])->name('auth.profile.avatar');

        // Password change route (authenticated users only)
        Route::post('change-password', [AuthController::class, 'changePassword'])
            ->middleware('auth:sanctum')
            ->name('auth.change-password');
    });

    // Sales & Pricing Engine Routes
    // All routes have auth:sanctum and locale middleware applied in controllers

    // Lead Management Routes
    Route::middleware('simplified.disabled:sales')->group(function () {
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
        Route::get('leads/{lead}/can-convert', [LeadConversionController::class, 'canConvert'])->name('leads.can-convert');
        Route::post('leads/{lead}/convert', [LeadConversionController::class, 'convert'])->name('leads.convert');

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

        // Audit Trail Routes
        Route::get('leads/{lead}/history', [LeadHistoryController::class, 'leadHistory'])->name('leads.history');
        Route::get('leads/{lead}/full-history', [LeadHistoryController::class, 'leadFullHistory'])->name('leads.full-history');
        Route::get('leads/{lead}/history-stats', [LeadHistoryController::class, 'leadHistoryStats'])->name('leads.history-stats');
        Route::get('proposals/{proposal}/history', [LeadHistoryController::class, 'proposalHistory'])->name('proposals.history');
        Route::get('quotations/{quotation}/history', [LeadHistoryController::class, 'quotationHistory'])->name('quotations.history');
        Route::get('audit/recent', [LeadHistoryController::class, 'recentActivity'])->name('audit.recent');
        Route::get('audit/user/{userId}', [LeadHistoryController::class, 'userActivity'])->name('audit.user');
    });

    // Client Management Routes
    Route::apiResource('clients', ClientController::class);

    // Client Portal Access Routes
    Route::post('clients/{client}/portal-access', [ClientPortalAccessController::class, 'create'])->name('clients.portal-access.create');
    Route::delete('clients/{client}/portal-access', [ClientPortalAccessController::class, 'revoke'])->name('clients.portal-access.revoke');
    Route::post('clients/{client}/portal-access/reset-password', [ClientPortalAccessController::class, 'resetPassword'])->name('clients.portal-access.reset-password');

    // Proposal Management Routes (Technical Proposals)
    Route::middleware('simplified.disabled:proposals')->group(function () {
        Route::apiResource('proposals', ProposalController::class);
        Route::post('proposals/fast-track', [ProposalController::class, 'fastTrack'])->name('proposals.fast-track');
        Route::post('proposals/{proposal}/upload', [ProposalController::class, 'uploadFile'])->name('proposals.upload');
        Route::post('proposals/{proposal}/send', [ProposalController::class, 'send'])->name('proposals.send');
        Route::post('proposals/{proposal}/approve', [ProposalController::class, 'approve'])->name('proposals.approve');
        Route::post('proposals/{proposal}/reject', [ProposalController::class, 'reject'])->name('proposals.reject');
        Route::get('proposals-expiring', [ProposalController::class, 'expiring'])->name('proposals.expiring');
    });

    // Quotation Management Routes (Price Quotes)
    Route::middleware('simplified.disabled:quotations')->group(function () {
        Route::apiResource('quotations', QuotationController::class);
        Route::post('quotations/calculate', [QuotationController::class, 'calculate'])->name('quotations.calculate');
        Route::post('quotations/validate-payment-plan', [QuotationController::class, 'validatePaymentPlan'])->name('quotations.validate-payment-plan');
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
        Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.update-status');
        Route::get('quotations-expiring', [QuotationController::class, 'expiring'])->name('quotations.expiring');
        Route::get('quotations-overdue', [QuotationController::class, 'overdue'])->name('quotations.overdue');
    });

    // Contract Management Routes
    Route::middleware('simplified.disabled:contracts')->group(function () {
        Route::get('contracts/milestones/due', [ContractController::class, 'dueMilestones'])->name('contracts.milestones.due');
        Route::patch('contracts/milestones/{milestone}/paid', [ContractController::class, 'markMilestonePaid'])->name('contracts.milestones.paid');
        Route::post('contracts', [ContractController::class, 'store'])->name('contracts.store');
        Route::post('contracts/from-quotation/{quotation}', [ContractController::class, 'createFromQuotation'])->name('contracts.create-from-quotation');
        Route::post('quotations/{quotation}/contract', [ContractController::class, 'createFromQuotation'])->name('contracts.generate-from-quotation');
        Route::post('contracts/{contract}/sign', [ContractController::class, 'sign'])->name('contracts.sign');
        Route::patch('contracts/{contract}/sign', [ContractController::class, 'signPatch'])->name('contracts.sign.patch');
        Route::post('contracts/{contract}/activate', [ContractController::class, 'activate'])->name('contracts.activate');
        Route::patch('contracts/{contract}/status', [ContractController::class, 'updateStatus'])->name('contracts.update-status');
        Route::get('contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
        Route::put('contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
        Route::delete('contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');
    });

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
    Route::prefix('templates')->middleware('simplified.disabled:proposals')->group(function () {
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
    Route::prefix('analytics')->middleware('simplified.disabled:advanced_analytics')->group(function () {
        Route::get('sales/performance', [SalesAnalyticsController::class, 'performance'])->name('analytics.sales.performance');
        Route::get('sales/sources', [SalesAnalyticsController::class, 'sources'])->name('analytics.sales.sources');
        Route::get('sales/pipeline', [SalesAnalyticsController::class, 'pipeline'])->name('analytics.sales.pipeline');
        Route::get('revenue/forecast', [SalesAnalyticsController::class, 'forecast'])->name('analytics.revenue.forecast');
        Route::get('quotations/stats', [SalesAnalyticsController::class, 'quotationStats'])->name('analytics.quotations.stats');
        Route::get('projects/types', [SalesAnalyticsController::class, 'projectTypes'])->name('analytics.projects.types');
    });

    // Executive Analytics Routes (Admin/Manager Only)
    Route::prefix('analytics')->middleware(['auth:sanctum', 'locale', 'simplified.disabled:advanced_analytics'])->group(function () {
        Route::get('sales-performance', [AnalyticsController::class, 'salesPerformance'])->name('analytics.sales-performance');
        Route::get('rejection-reasons', [AnalyticsController::class, 'rejectionReasons'])->name('analytics.rejection-reasons');
        Route::get('financial-forecast', [AnalyticsController::class, 'financialForecast'])->name('analytics.financial-forecast');
        Route::get('follow-up-radar', [AnalyticsController::class, 'followUpRadar'])->name('analytics.follow-up-radar');
        Route::get('dashboard-summary', [AnalyticsController::class, 'dashboardSummary'])->name('analytics.dashboard-summary');
        Route::post('clear-cache', [AnalyticsController::class, 'clearCache'])->name('analytics.clear-cache');
    });

    // Time Entry Routes
    Route::post('time-entries/start', [TimeEntryController::class, 'start'])->name('time-entries.start');
    Route::post('time-entries/heartbeat', [TimeEntryController::class, 'heartbeat'])->name('time-entries.heartbeat');
    Route::post('time-entries/pause', [TimeEntryController::class, 'pause'])->name('time-entries.pause');
    Route::post('time-entries/hold', [TimeEntryController::class, 'hold'])->name('time-entries.hold');
    Route::post('time-entries/end', [TimeEntryController::class, 'end'])->name('time-entries.end');
    Route::post('time-entries/{id}/resume', [TimeEntryController::class, 'resume'])->name('time-entries.resume');
    Route::get('time-entries/resumable', [TimeEntryController::class, 'resumable'])->name('time-entries.resumable');
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
    Route::post('attendance/{id}/deduction', [AttendanceController::class, 'addDeduction'])->name('attendance.add-deduction');
    Route::post('attendance/{id}/grace-day', [AttendanceController::class, 'markGraceDay'])->name('attendance.mark-grace-day');
    Route::delete('attendance/{id}/grace-day', [AttendanceController::class, 'removeGraceDay'])->name('attendance.remove-grace-day');

    // Employee Management Routes
    Route::apiResource('employees', EmployeeController::class)->except(['destroy']);
    Route::delete('employees/{id}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::post('employees/{id}/avatar', [EmployeeController::class, 'updateAvatar'])->name('employees.avatar');
    Route::get('employees/{id}/performance', [EmployeeController::class, 'performance'])->middleware('simplified.disabled:performance')->name('employees.performance');
    Route::post('employees/{id}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
    Route::post('employees/{id}/activate', [EmployeeController::class, 'activate'])->name('employees.activate');

    require __DIR__.'/hr.php';

    // Project Management Routes
    // All routes have auth:sanctum and locale middleware applied in controllers

    // Project CRUD Routes
    Route::get('workflow-templates', [ProjectPipelineController::class, 'templates'])->name('workflow-templates.index');
    Route::post('workflow-templates', [ProjectPipelineController::class, 'storeTemplate'])->name('workflow-templates.store');
    Route::get('projects/{project}/pipeline', [ProjectPipelineController::class, 'show'])->name('projects.pipeline.show');
    Route::post('projects/{project}/pipeline/tasks', [ProjectPipelineController::class, 'storeTask'])->name('projects.pipeline.tasks.store');
    Route::patch('projects/{project}/pipeline/tasks/{task}', [ProjectPipelineController::class, 'updateTask'])->name('projects.pipeline.tasks.update');
    Route::delete('projects/{project}/pipeline/tasks/{task}', [ProjectPipelineController::class, 'destroyTask'])->name('projects.pipeline.tasks.destroy');
    Route::post('projects/{project}/pipeline/tasks/{task}/cover', [ProjectPipelineController::class, 'storeTaskCover'])->name('projects.pipeline.tasks.cover.store');
    Route::delete('projects/{project}/pipeline/tasks/{task}/cover', [ProjectPipelineController::class, 'destroyTaskCover'])->name('projects.pipeline.tasks.cover.destroy');
    Route::post('projects/{project}/pipeline/tasks/{task}/references', [ProjectPipelineController::class, 'storeTaskReferences']);
    Route::delete('projects/{project}/pipeline/tasks/{task}/references/{reference}', [ProjectPipelineController::class, 'destroyTaskReference']);
    Route::patch('projects/{project}/pipeline/tasks/{task}/stage', [ProjectPipelineController::class, 'moveTask'])->name('projects.pipeline.tasks.move');
    Route::put('projects/{project}/pipeline/stages', [ProjectPipelineController::class, 'updateStages'])->name('projects.pipeline.stages.update');
    Route::post('projects/{project}/pipeline/templates/{template}', [ProjectPipelineController::class, 'applyTemplate'])->name('projects.pipeline.templates.apply');

    Route::get('projects/trash', [ProjectController::class, 'trash'])->name('projects.trash');
    Route::post('projects/{id}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
    Route::delete('projects/{id}/permanent', [ProjectController::class, 'forceDestroy'])->name('projects.permanent');
    Route::apiResource('projects', ProjectController::class);

    // Pinned Projects Routes
    Route::get('projects/pinned', [ProjectController::class, 'pinned'])->name('projects.pinned');
    Route::post('projects/{project}/pin', [ProjectController::class, 'pin'])->name('projects.pin');
    Route::delete('projects/{project}/pin', [ProjectController::class, 'unpin'])->name('projects.unpin');

    // Project Status Update Route (flexible status transitions)
    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.update-status');

    // Project Conversion Route (convert quotation to project)
    Route::post('projects/convert', [ProjectController::class, 'convert'])->middleware('simplified.disabled:quotations')->name('projects.convert');

    // Direct Project Creation Route (create project for client without quotation)
    Route::post('projects/direct', [ProjectController::class, 'storeDirect'])->name('projects.store-direct');

    // Project UI Approval Route (manual approval of UI/UX deliverables)
    Route::post('projects/{project}/approve-ui', [ProjectController::class, 'approveUI'])->middleware('simplified.disabled:ui_approval')->name('projects.approve-ui');

    // Project History Route (audit trail)
    Route::get('projects/{project}/history', [ProjectHistoryController::class, 'index'])->name('projects.history');

    // Google Drive project files
    Route::get('google-drive/status', [GoogleDriveController::class, 'status'])->name('google-drive.status');
    Route::get('google-drive/oauth-url', [GoogleDriveController::class, 'oauthUrl'])->name('google-drive.oauth-url');
    Route::post('google-drive/oauth-callback', [GoogleDriveController::class, 'oauthCallback'])->name('google-drive.oauth-callback');
    Route::get('projects/{project}/drive-folders', [GoogleDriveController::class, 'folders'])->name('projects.drive-folders');
    Route::post('projects/{project}/drive-folder', [GoogleDriveController::class, 'connectFolder'])->name('projects.drive-folder');
    Route::get('projects/{project}/drive-files', [GoogleDriveController::class, 'files'])->name('projects.drive-files');
    Route::post('projects/{project}/drive-files', [GoogleDriveController::class, 'upload'])->name('projects.drive-files.upload');

    // Access Control Devices (fingerprint / card terminals)
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::post('devices/{device}/test', [DeviceController::class, 'test'])->name('devices.test');
    Route::post('devices/{device}/poll', [DeviceController::class, 'poll'])->name('devices.poll');
    Route::post('devices/{device}/users/push', [DeviceController::class, 'pushUsers'])->name('devices.users.push');
    Route::get('devices/{device}/users/pull', [DeviceController::class, 'pullUsers'])->name('devices.users.pull');
    Route::post('devices/{device}/users', [DeviceController::class, 'linkUser'])->name('devices.users.link');
    Route::delete('devices/{device}/users/{deviceUser}', [DeviceController::class, 'unlinkUser'])->name('devices.users.unlink');
    Route::get('devices/{device}/events', [DeviceController::class, 'events'])->name('devices.events');
    Route::post('devices/{device}/webhook/enable', [DeviceController::class, 'enableWebhook'])->name('devices.webhook.enable');
    Route::post('devices/{device}/webhook/disable', [DeviceController::class, 'disableWebhook'])->name('devices.webhook.disable');

    // Project Wiki Routes (nested resource)
    Route::apiResource('projects.wikis', ProjectWikiController::class);

    // Project Files Routes
    Route::post('projects/{project}/files/{file}/replace', [ProjectFileController::class, 'replace'])->name('projects.files.replace');
    Route::apiResource('projects.files', ProjectFileController::class);

    // Project Members Routes
    Route::apiResource('projects.members', ProjectMemberController::class)->except(['show']);

    // Task Management Routes
    // All routes have auth:sanctum and locale middleware applied in controller

    // My Assigned Tasks Route (tasks where user is team member) - MUST be before apiResource
    Route::get('tasks/dashboard-list', [TaskController::class, 'dashboardTasks'])->name('tasks.dashboard-list');
    Route::get('tasks/my-assigned', [TaskController::class, 'myAssignedTasks'])->name('tasks.my-assigned');

    // Task Supervision Routes
    Route::put('tasks/{task}/supervisor', [TaskSupervisionController::class, 'setSupervisor'])->name('tasks.supervisor.update');
    Route::get('tasks/{task}/supervision', [TaskSupervisionController::class, 'show'])->name('tasks.supervision.show');
    Route::post('tasks/{task}/help-requests', [TaskSupervisionController::class, 'storeHelpRequest'])->name('tasks.help-requests.store');
    Route::post('tasks/{task}/supervision-sessions/start', [TaskSupervisionController::class, 'startSession'])->name('tasks.supervision-sessions.start');
    Route::patch('task-help-requests/{helpRequest}', [TaskSupervisionController::class, 'updateHelpRequest'])->name('task-help-requests.update');
    Route::get('task-supervision-sessions/active', [TaskSupervisionController::class, 'active'])->name('task-supervision-sessions.active');
    Route::post('task-supervision-sessions/{session}/heartbeat', [TaskSupervisionController::class, 'heartbeat'])->name('task-supervision-sessions.heartbeat');
    Route::post('task-supervision-sessions/{session}/stop', [TaskSupervisionController::class, 'stopSession'])->name('task-supervision-sessions.stop');
    Route::get('task-supervision/summary', [TaskSupervisionController::class, 'summary'])->name('task-supervision.summary');

    // Task CRUD Routes
    Route::apiResource('tasks', TaskController::class);

    // Task Dependencies Routes
    Route::get('tasks/{task}/dependencies', [TaskDependencyController::class, 'index'])->name('tasks.dependencies.index');
    Route::post('tasks/{task}/dependencies', [TaskDependencyController::class, 'store'])->name('tasks.dependencies.store');
    Route::delete('task-dependencies/{dependency}', [TaskDependencyController::class, 'destroy'])->name('task-dependencies.destroy');

    // Task Templates Routes
    Route::apiResource('task-templates', TaskTemplateController::class);
    Route::post('task-templates/{template}/instantiate', [TaskTemplateController::class, 'instantiate'])->name('task-templates.instantiate');

    // Recurring Tasks Routes
    Route::apiResource('recurring-tasks', RecurringTaskController::class);
    Route::post('recurring-tasks/{config}/generate', [RecurringTaskController::class, 'generate'])->name('recurring-tasks.generate');

    // Task Status Update Route (flexible status transitions)
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.update-status');

    // Task Mark Complete Route (with timeline validation)
    Route::post('tasks/{task}/mark-complete', [TaskController::class, 'markComplete'])->name('tasks.mark-complete');

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
    Route::get('projects/{projectId}/points-summary', [TaskController::class, 'pointsSummary'])->middleware('simplified.disabled:points')->name('projects.points-summary');

    // Task Deliverables Routes
    // All routes have auth:sanctum and locale middleware applied in controller
    Route::middleware('simplified.disabled:ui_approval')->group(function () {
        Route::get('tasks/{taskId}/deliverables', [TaskDeliverableController::class, 'index'])->name('tasks.deliverables.index');
        Route::post('tasks/{taskId}/deliverables', [TaskDeliverableController::class, 'store'])->name('tasks.deliverables.store');
        Route::get('tasks/{taskId}/deliverables/{deliverableId}', [TaskDeliverableController::class, 'show'])->name('tasks.deliverables.show');
        Route::put('tasks/{taskId}/deliverables/{deliverableId}', [TaskDeliverableController::class, 'update'])->name('tasks.deliverables.update');
        Route::delete('tasks/{taskId}/deliverables/{deliverableId}', [TaskDeliverableController::class, 'destroy'])->name('tasks.deliverables.destroy');
        Route::post('tasks/{taskId}/deliverables/{deliverableId}/submit', [TaskDeliverableController::class, 'submit'])->name('tasks.deliverables.submit');
        Route::post('tasks/{taskId}/deliverables/{deliverableId}/review', [TaskDeliverableController::class, 'review'])->name('tasks.deliverables.review');
    });

    // Client Portal Routes
    // All routes have auth:sanctum, locale, and role:client middleware applied in controllers
    // ClientScope automatically filters data to client's own records
    Route::prefix('client')->middleware(['auth:sanctum', 'locale', 'role:client'])->group(function () {
        // Client Project Routes
        Route::get('projects', [ClientProjectController::class, 'index'])->name('client.projects.index');
        Route::get('projects/{project}', [ClientProjectController::class, 'show'])->name('client.projects.show');
        Route::post('projects/{project}/approve-ui', [ClientProjectController::class, 'approveUI'])->middleware('simplified.disabled:ui_approval')->name('client.projects.approve-ui');

        // Client Invoice Routes
        Route::middleware('simplified.disabled:client_billing')->group(function () {
            Route::get('invoices', [ClientInvoiceController::class, 'index'])->name('client.invoices.index');
            Route::get('invoices/{invoice}', [ClientInvoiceController::class, 'show'])->name('client.invoices.show');
            Route::post('invoices/{invoice}/pay', [ClientInvoiceController::class, 'pay'])->name('client.invoices.pay');
        });
    });

    // Admin Invoice Management Routes
    // All routes have auth:sanctum and locale middleware applied at route level
    Route::apiResource('invoices', InvoiceController::class)->middleware(['auth:sanctum', 'locale', 'simplified.disabled:billing']);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware(['auth:sanctum', 'locale', 'simplified.disabled:billing'])->name('invoices.send');
    Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])->middleware(['auth:sanctum', 'locale', 'simplified.disabled:billing'])->name('invoices.mark-paid');
    Route::post('projects/{project}/generate-milestone-invoice', [InvoiceController::class, 'generateMilestoneInvoice'])->middleware(['auth:sanctum', 'locale', 'simplified.disabled:billing'])->name('projects.generate-milestone-invoice');
    Route::post('projects/{project}/generate-pending-invoices', [InvoiceController::class, 'generatePendingInvoices'])->middleware(['auth:sanctum', 'locale', 'simplified.disabled:billing'])->name('projects.generate-pending-invoices');

    // Milestone Management Routes
    Route::prefix('milestones')->middleware(['auth:sanctum', 'locale', 'simplified.disabled:billing'])->group(function () {
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
            Route::get('profitability', [AdminStatsController::class, 'profitability'])->middleware('simplified.disabled:financial_reports')->name('admin.stats.profitability');
            Route::get('quality', [AdminStatsController::class, 'quality'])->middleware('simplified.disabled:code_reviews')->name('admin.stats.quality');
            Route::get('user-performance', [AdminStatsController::class, 'userPerformance'])->middleware('simplified.disabled:performance')->name('admin.stats.user-performance');
            Route::get('attendance', [AdminStatsController::class, 'attendance'])->name('admin.stats.attendance');
            // More specific route first
            Route::get('employee-review/{userId}/export', [AdminStatsController::class, 'exportEmployeeReview'])->middleware('simplified.disabled:employee_reviews')->name('admin.stats.employee-review.export');
            Route::get('employee-review/{userId}', [AdminStatsController::class, 'employeeReview'])->middleware('simplified.disabled:employee_reviews')->name('admin.stats.employee-review');
        });

        // Admin Role Management Routes
        Route::apiResource('roles', AdminRoleController::class);
        Route::get('permissions', [AdminRoleController::class, 'permissions'])->name('admin.permissions');

        // Admin User Management Routes (Internal Costs)
        Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::put('users/{id}/internal-cost', [AdminUserController::class, 'updateInternalCost'])->middleware('simplified.disabled:internal_costs')->name('admin.users.update-internal-cost');
        Route::put('users/internal-costs', [AdminUserController::class, 'bulkUpdateInternalCosts'])->middleware('simplified.disabled:internal_costs')->name('admin.users.bulk-update-internal-costs');

        // User Management Routes (CRUD)
        Route::apiResource('users/manage', UserController::class)->parameters(['manage' => 'user']);
        Route::post('users/manage/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggle-status');
        Route::put('users/manage/{user}/roles', [UserController::class, 'assignRoles'])->name('admin.users.assign-roles');

        // Admin Audit Log Routes
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
        Route::get('audit-logs/action-types', [AuditLogController::class, 'actionTypes'])->name('admin.audit-logs.action-types');
        Route::get('audit-logs/stats', [AuditLogController::class, 'stats'])->name('admin.audit-logs.stats');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');

        // Admin Deliverable Management Routes (Full Control)
        Route::prefix('deliverables')->middleware('simplified.disabled:ui_approval')->group(function () {
            Route::put('{id}', [AdminDeliverableController::class, 'update'])->name('admin.deliverables.update');
            Route::delete('{id}', [AdminDeliverableController::class, 'destroy'])->name('admin.deliverables.destroy');
            Route::patch('{id}/status', [AdminDeliverableController::class, 'changeStatus'])->name('admin.deliverables.change-status');
            Route::patch('{id}/reset', [AdminDeliverableController::class, 'reset'])->name('admin.deliverables.reset');
            Route::post('{id}/force-approve', [AdminDeliverableController::class, 'forceApprove'])->name('admin.deliverables.force-approve');
            Route::post('{id}/force-reject', [AdminDeliverableController::class, 'forceReject'])->name('admin.deliverables.force-reject');
        });
    });

    // Performance Evaluation Routes
    Route::prefix('performance')->middleware('simplified.disabled:performance')->group(function () {
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
    Route::prefix('code-reviews')->middleware('simplified.disabled:code_reviews')->group(function () {
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
    Route::prefix('innovation-logs')->middleware('simplified.disabled:innovation')->group(function () {
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
    Route::prefix('user-points')->middleware('simplified.disabled:points')->group(function () {
        Route::get('/transaction-types', [UserPointsController::class, 'getTransactionTypes'])->name('user-points.transaction-types');
        Route::get('/', [UserPointsController::class, 'index'])->name('user-points.index');
        Route::post('/add', [UserPointsController::class, 'addPoints'])->name('user-points.add');
        Route::post('/deduct', [UserPointsController::class, 'deductPoints'])->name('user-points.deduct');
        Route::post('/set', [UserPointsController::class, 'setPoints'])->name('user-points.set');
        Route::get('/history/{userId}', [UserPointsController::class, 'history'])->name('user-points.history');
        Route::get('/unified-history/{userId}', [UserPointsController::class, 'getUnifiedHistory'])->name('user-points.unified-history');
        Route::delete('/transaction/{transactionId}', [UserPointsController::class, 'deleteTransaction'])->name('user-points.delete-transaction');
        Route::get('/leaderboard', [UserPointsController::class, 'leaderboard'])->name('user-points.leaderboard');
        Route::get('/statistics', [UserPointsController::class, 'statistics'])->name('user-points.statistics');
        Route::get('/report', [UserPointsController::class, 'report'])->name('user-points.report');
    });

    // Enum Routes
    Route::prefix('enums')->group(function () {
        Route::get('user-specializations', [EnumController::class, 'getUserSpecializations'])->name('enums.user-specializations');
        Route::get('task-categories', [EnumController::class, 'getTaskCategories'])->name('enums.task-categories');
        Route::get('compatible-task-categories/{specialization}', [EnumController::class, 'getCompatibleTaskCategories'])->name('enums.compatible-task-categories');
        Route::get('project-statuses', [EnumController::class, 'getProjectStatuses'])->name('enums.project-statuses');
        Route::get('task-statuses', [EnumController::class, 'getTaskStatuses'])->name('enums.task-statuses');
    });

    // Settings Routes (Admin only)
    Route::prefix('settings')->group(function () {
        Route::get('lookup-options', [LookupOptionController::class, 'index'])->name('settings.lookup-options.index');
        Route::post('lookup-options', [LookupOptionController::class, 'store'])->name('settings.lookup-options.store');
        Route::put('lookup-options/reorder', [LookupOptionController::class, 'reorder'])->name('settings.lookup-options.reorder');
        Route::put('lookup-options/{lookupOption}', [LookupOptionController::class, 'update'])->name('settings.lookup-options.update');
        Route::delete('lookup-options/{lookupOption}', [LookupOptionController::class, 'destroy'])->name('settings.lookup-options.destroy');

        Route::get('tags', [TagController::class, 'index'])->name('settings.tags.index');
        Route::post('tags', [TagController::class, 'store'])->name('settings.tags.store');
        Route::put('tags/{tag}', [TagController::class, 'update'])->name('settings.tags.update');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('settings.tags.destroy');
    });

    // Sales Dashboard Routes (Manager Dashboard)
    Route::prefix('sales-dashboard')->middleware('simplified.disabled:sales')->group(function () {
        Route::get('summary', [SalesDashboardController::class, 'summary'])->name('sales-dashboard.summary');
        Route::get('pipeline', [SalesDashboardController::class, 'pipeline'])->name('sales-dashboard.pipeline');
        Route::get('team-performance', [SalesDashboardController::class, 'teamPerformance'])->name('sales-dashboard.team-performance');
        Route::get('bottlenecks', [SalesDashboardController::class, 'bottlenecks'])->name('sales-dashboard.bottlenecks');
        Route::get('alerts', [SalesDashboardController::class, 'alerts'])->name('sales-dashboard.alerts');
    });

    // Global Search Route
    Route::get('search', [GlobalSearchController::class, 'search'])->name('search');

    // Export Routes
    Route::prefix('export')->middleware('simplified.disabled:sales_exports')->group(function () {
        Route::get('leads', [ExportController::class, 'exportLeads'])->name('export.leads');
        Route::get('proposals', [ExportController::class, 'exportProposals'])->name('export.proposals');
        Route::get('quotations', [ExportController::class, 'exportQuotations'])->name('export.quotations');
        Route::get('sales-report', [ExportController::class, 'salesReport'])->name('export.sales-report');
    });

});
