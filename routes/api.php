<?php

use App\Http\Controllers\Api\V1\AdminStatsController;
use App\Http\Controllers\Api\V1\AdminRoleController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Client\ClientInvoiceController;
use App\Http\Controllers\Api\V1\Client\ClientProjectController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectFileController;
use App\Http\Controllers\Api\V1\ProjectHistoryController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\ProjectWikiController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TimeEntryController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\DashboardController;
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
    
    // Client Management Routes
    Route::apiResource('clients', ClientController::class);
    
    // Quotation Management Routes
    Route::apiResource('quotations', QuotationController::class);
    Route::post('quotations/calculate', [QuotationController::class, 'calculate'])->name('quotations.calculate');
    
    // Time Entry Routes
    Route::post('time-entries/start', [TimeEntryController::class, 'start'])->name('time-entries.start');
    Route::post('time-entries/stop', [TimeEntryController::class, 'stop'])->name('time-entries.stop');
    Route::get('time-entries', [TimeEntryController::class, 'index'])->name('time-entries.index');
    Route::get('time-entries/active', [TimeEntryController::class, 'active'])->name('time-entries.active');

    // Attendance Routes
    // All routes have auth:sanctum and locale middleware applied in controller
    Route::get('attendance/today', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::get('attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');

    // Project Management Routes
    // All routes have auth:sanctum and locale middleware applied in controllers
    
    // Project CRUD Routes
    Route::apiResource('projects', ProjectController::class);
    
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
    
    // Task CRUD Routes
    Route::apiResource('tasks', TaskController::class);
    
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

    // Dashboard Routes
    Route::get('dashboard/overview', [DashboardController::class, 'overview'])->name('dashboard.overview');

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
});
