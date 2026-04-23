<?php

use App\Modules\Client\Controllers\ClientController;
use App\Modules\Client\Controllers\ClientInvoiceController;
use App\Modules\Client\Controllers\ClientPortalAccessController;
use App\Modules\Client\Controllers\ClientProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('clients', ClientController::class)
        ->middleware(['auth:sanctum', 'locale']);

    Route::middleware(['auth:sanctum', 'locale'])->group(function () {
        Route::post('clients/{client}/portal-access', [ClientPortalAccessController::class, 'create'])
            ->name('clients.portal-access.create');
        Route::delete('clients/{client}/portal-access', [ClientPortalAccessController::class, 'revoke'])
            ->name('clients.portal-access.revoke');
        Route::post('clients/{client}/portal-access/reset-password', [ClientPortalAccessController::class, 'resetPassword'])
            ->name('clients.portal-access.reset-password');
    });

    Route::prefix('client')
        ->middleware(['auth:sanctum', 'locale', 'role:client'])
        ->group(function () {
            Route::get('projects', [ClientProjectController::class, 'index'])
                ->name('client.projects.index');
            Route::get('projects/{project}', [ClientProjectController::class, 'show'])
                ->name('client.projects.show');
            Route::post('projects/{project}/approve-ui', [ClientProjectController::class, 'approveUI'])
                ->name('client.projects.approve-ui');

            Route::get('invoices', [ClientInvoiceController::class, 'index'])
                ->name('client.invoices.index');
            Route::get('invoices/{invoice}', [ClientInvoiceController::class, 'show'])
                ->name('client.invoices.show');
            Route::post('invoices/{invoice}/pay', [ClientInvoiceController::class, 'pay'])
                ->name('client.invoices.pay');
        });
});
