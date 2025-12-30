<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectHistoryController;
use App\Http\Controllers\Api\V1\ProjectWikiController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\TimeEntryController;
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
    });

    // Sales & Pricing Engine Routes
    // All routes have auth:sanctum and locale middleware applied in controllers
    
    // Lead Management Routes
    Route::apiResource('leads', LeadController::class);
    
    // Quotation Management Routes
    Route::apiResource('quotations', QuotationController::class);
    Route::post('quotations/calculate', [QuotationController::class, 'calculate'])->name('quotations.calculate');
    
    // Time Entry Routes
    Route::post('time-entries/start', [TimeEntryController::class, 'start'])->name('time-entries.start');
    Route::post('time-entries/stop', [TimeEntryController::class, 'stop'])->name('time-entries.stop');
    Route::get('time-entries', [TimeEntryController::class, 'index'])->name('time-entries.index');
    Route::get('time-entries/active', [TimeEntryController::class, 'active'])->name('time-entries.active');

    // Project Management Routes
    // All routes have auth:sanctum and locale middleware applied in controllers
    
    // Project CRUD Routes
    Route::apiResource('projects', ProjectController::class);
    
    // Project Conversion Route (convert quotation to project)
    Route::post('projects/convert', [ProjectController::class, 'convert'])->name('projects.convert');
    
    // Project UI Approval Route (manual approval of UI/UX deliverables)
    Route::post('projects/{project}/approve-ui', [ProjectController::class, 'approveUI'])->name('projects.approve-ui');
    
    // Project History Route (audit trail)
    Route::get('projects/{project}/history', [ProjectHistoryController::class, 'index'])->name('projects.history');
    
    // Project Wiki Routes (nested resource)
    Route::apiResource('projects.wikis', ProjectWikiController::class);
});
