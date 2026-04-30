<?php

use App\Modules\Settings\Controllers\AdminRoleController;
use App\Modules\Settings\Controllers\AdminUserController;
use App\Modules\Settings\Controllers\DictionaryController;
use App\Modules\Settings\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'locale'])
    ->group(function (): void {
        Route::controller(DictionaryController::class)
            ->prefix('settings/{entity}')
            ->name('settings.dictionary.')
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{id}', 'update')->name('update');
                Route::patch('/{id}', 'update')->name('patch');
                Route::delete('/{id}', 'destroy')->name('destroy');
            });

        Route::get('internal-users', [UserManagementController::class, 'internalUsers'])
            ->name('internal-users.index');

        Route::prefix('admin')->group(function () {
            Route::apiResource('roles', AdminRoleController::class);
            Route::get('permissions', [AdminRoleController::class, 'permissions'])->name('admin.permissions');

            Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
            Route::put('users/{id}/internal-cost', [AdminUserController::class, 'updateInternalCost'])->name('admin.users.update-internal-cost');
            Route::put('users/internal-costs', [AdminUserController::class, 'bulkUpdateInternalCosts'])->name('admin.users.bulk-update-internal-costs');

            Route::apiResource('users/manage', UserManagementController::class)->parameters(['manage' => 'user']);
            Route::post('users/manage/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('admin.users.toggle-status');
            Route::put('users/manage/{user}/roles', [UserManagementController::class, 'assignRoles'])->name('admin.users.assign-roles');
        });
    });
