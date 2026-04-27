<?php

use App\Modules\Contract\Controllers\ContractController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
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
