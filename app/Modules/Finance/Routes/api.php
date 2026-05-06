<?php

use App\Modules\Finance\Controllers\FinanceInvoiceController;
use App\Modules\Finance\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'locale'])->group(function () {

    // ── Finance Invoices ──────────────────────────────────────────────────────
    Route::middleware('permission:manage_invoices')->group(function () {
        Route::get('finance/invoices', [FinanceInvoiceController::class, 'index'])
            ->name('finance.invoices.index');

        Route::post('finance/invoices', [FinanceInvoiceController::class, 'store'])
            ->name('finance.invoices.store');

        Route::get('finance/invoices/{invoice}', [FinanceInvoiceController::class, 'show'])
            ->name('finance.invoices.show');

        Route::put('finance/invoices/{invoice}', [FinanceInvoiceController::class, 'update'])
            ->name('finance.invoices.update');

        Route::patch('finance/invoices/{invoice}', [FinanceInvoiceController::class, 'update'])
            ->name('finance.invoices.patch');

        Route::delete('finance/invoices/{invoice}', [FinanceInvoiceController::class, 'destroy'])
            ->name('finance.invoices.destroy');
    });

    // ── SaaS Subscriptions ────────────────────────────────────────────────────
    Route::middleware('permission:manage_subscriptions')->group(function () {
        Route::get('finance/subscriptions', [SubscriptionController::class, 'index'])
            ->name('finance.subscriptions.index');

        Route::post('finance/subscriptions', [SubscriptionController::class, 'store'])
            ->name('finance.subscriptions.store');

        Route::get('finance/subscriptions/{subscription}', [SubscriptionController::class, 'show'])
            ->name('finance.subscriptions.show');

        Route::put('finance/subscriptions/{subscription}', [SubscriptionController::class, 'update'])
            ->name('finance.subscriptions.update');

        Route::patch('finance/subscriptions/{subscription}', [SubscriptionController::class, 'update'])
            ->name('finance.subscriptions.patch');

        Route::delete('finance/subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])
            ->name('finance.subscriptions.destroy');
    });
});
