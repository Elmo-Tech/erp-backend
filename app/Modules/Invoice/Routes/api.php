<?php

use App\Modules\Invoice\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('invoices', InvoiceController::class)
        ->middleware(['auth:sanctum', 'locale']);

    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])
        ->middleware(['auth:sanctum', 'locale'])
        ->name('invoices.download');

    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])
        ->middleware(['auth:sanctum', 'locale'])
        ->name('invoices.send');

    Route::match(['post', 'patch'], 'invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])
        ->middleware(['auth:sanctum', 'locale'])
        ->name('invoices.mark-paid');

    Route::post('projects/{project}/generate-milestone-invoice', [InvoiceController::class, 'generateMilestoneInvoice'])
        ->middleware(['auth:sanctum', 'locale'])
        ->name('projects.generate-milestone-invoice');

    Route::post('projects/{project}/generate-pending-invoices', [InvoiceController::class, 'generatePendingInvoices'])
        ->middleware(['auth:sanctum', 'locale'])
        ->name('projects.generate-pending-invoices');
});
