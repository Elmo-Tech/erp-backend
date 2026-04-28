<?php

use App\Modules\User\Controllers\AdminRoleController;
use App\Modules\User\Controllers\AdminUserController;
use App\Modules\User\Controllers\UserController;
use App\Modules\User\Controllers\UserPointsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::apiResource('roles', AdminRoleController::class);
        Route::get('permissions', [AdminRoleController::class, 'permissions'])->name('admin.permissions');

        Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::put('users/{id}/internal-cost', [AdminUserController::class, 'updateInternalCost'])->name('admin.users.update-internal-cost');
        Route::put('users/internal-costs', [AdminUserController::class, 'bulkUpdateInternalCosts'])->name('admin.users.bulk-update-internal-costs');

        Route::apiResource('users/manage', UserController::class)->parameters(['manage' => 'user']);
        Route::post('users/manage/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggle-status');
        Route::put('users/manage/{user}/roles', [UserController::class, 'assignRoles'])->name('admin.users.assign-roles');
    });

    Route::prefix('user-points')->group(function () {
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
});
