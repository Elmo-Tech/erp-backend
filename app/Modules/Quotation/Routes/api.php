<?php

use App\Modules\Quotation\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('quotations', QuotationController::class);
    Route::post('quotations/calculate', [QuotationController::class, 'calculate'])->name('quotations.calculate');
    Route::post('quotations/validate-payment-plan', [QuotationController::class, 'validatePaymentPlan'])->name('quotations.validate-payment-plan');
    Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
    Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.update-status');
    Route::get('quotations-expiring', [QuotationController::class, 'expiring'])->name('quotations.expiring');
    Route::get('quotations-overdue', [QuotationController::class, 'overdue'])->name('quotations.overdue');
});
