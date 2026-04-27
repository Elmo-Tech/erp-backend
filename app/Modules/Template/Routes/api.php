<?php

use App\Modules\Template\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/templates')->group(function () {
    Route::get('proposals', [TemplateController::class, 'proposalTemplates'])->name('templates.proposals.index');
    Route::post('proposals', [TemplateController::class, 'storeProposalTemplate'])->name('templates.proposals.store');
    Route::put('proposals/{template}', [TemplateController::class, 'updateProposalTemplate'])->name('templates.proposals.update');
    Route::delete('proposals/{template}', [TemplateController::class, 'destroyProposalTemplate'])->name('templates.proposals.destroy');
    Route::post('proposals/{template}/generate', [TemplateController::class, 'generateProposal'])->name('templates.proposals.generate');

    Route::get('contracts', [TemplateController::class, 'contractTemplates'])->name('templates.contracts.index');
    Route::post('contracts', [TemplateController::class, 'storeContractTemplate'])->name('templates.contracts.store');
    Route::put('contracts/{template}', [TemplateController::class, 'updateContractTemplate'])->name('templates.contracts.update');
    Route::delete('contracts/{template}', [TemplateController::class, 'destroyContractTemplate'])->name('templates.contracts.destroy');
    Route::post('contracts/{template}/generate', [TemplateController::class, 'generateContract'])->name('templates.contracts.generate');
});
