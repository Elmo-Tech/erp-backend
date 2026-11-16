<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Middleware\EnsureActiveErpSession;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'elmo-erp-api',
    'version' => 'v2-foundation',
]));

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/mfa/status', [AuthController::class, 'mfaStatus']);
    Route::post('/mfa/enroll/start', [AuthController::class, 'enrollStart']);
    Route::post('/mfa/enroll/confirm', [AuthController::class, 'enrollConfirm']);
    Route::post('/mfa/verify', [AuthController::class, 'verify']);
});

Route::middleware(['auth:sanctum', EnsureActiveErpSession::class])->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies/switch', [CompanyController::class, 'switch']);
    Route::get('/companies/context', [CompanyController::class, 'context']);
    Route::get('/companies/authorize/{area}', [CompanyController::class, 'authorizeArea']);
});
