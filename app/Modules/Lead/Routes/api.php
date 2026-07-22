<?php

use App\Modules\Lead\Controllers\LeadContractController;
use App\Modules\Lead\Controllers\LeadController;
use App\Modules\Lead\Controllers\LeadConversionController;
use App\Modules\Lead\Controllers\LeadDocumentDownloadController;
use App\Modules\Lead\Controllers\LeadPipelineController;
use App\Modules\Lead\Controllers\LeadProposalController;
use App\Modules\Lead\Controllers\LeadProposalTemplateController;
use App\Modules\Lead\Controllers\LeadQuotationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('lead-proposal-templates', [LeadProposalTemplateController::class, 'index'])
        ->middleware('permission:view_leads|manage_leads')
        ->name('leads.proposal-templates.index');

    /*
    |--------------------------------------------------------------------------
    | Lead Pipeline Routes
    |--------------------------------------------------------------------------
    |
    | Granular action permissions are accepted alongside the manage_* umbrellas.
    |
    | The {lead} segment uses Laravel's implicit route model binding.
    |
    */

    Route::middleware('permission:view_leads|manage_leads')->group(function () {
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');

        Route::get('leads/{lead}/proposals', [LeadProposalController::class,  'index'])->name('leads.proposals.index');
    });

    Route::get('leads/{lead}/quotations', [LeadQuotationController::class, 'index'])
        ->middleware('permission:view_quotations|manage_leads')
        ->name('leads.quotations.index');
    Route::get('leads/{lead}/contracts', [LeadContractController::class, 'index'])
        ->middleware('permission:view_contracts|manage_contracts')
        ->name('leads.contracts.index');

    Route::post('leads', [LeadController::class, 'store'])
        ->middleware('permission:create_leads|manage_leads')
        ->name('leads.store');
    Route::delete('leads/{lead}/pipeline', [LeadController::class, 'destroy'])
        ->middleware('permission:delete_leads|manage_leads')
        ->name('leads.pipeline.destroy');

    Route::post('leads/{lead}/stage', [LeadPipelineController::class, 'updateStage'])
        ->middleware('permission:edit_leads|manage_leads')
        ->name('leads.stage.update');
    Route::get('leads/{lead}/pipeline/readiness', [LeadPipelineController::class, 'readiness'])
        ->middleware('permission:edit_leads|manage_leads')
        ->name('leads.pipeline.readiness');
    Route::post('leads/{lead}/convert', [LeadConversionController::class, 'convert'])
        ->middleware('permission:convert_leads|manage_leads')
        ->name('leads.convert');
    Route::get('leads/{lead}/documents/{documentType}/{documentId}/download', [LeadDocumentDownloadController::class, 'download'])
        ->name('leads.documents.download');

    Route::patch('leads/{lead}/pipeline/proposals/{proposal}', [LeadProposalController::class, 'update'])
        ->middleware('permission:edit_leads|manage_leads')
        ->name('leads.pipeline.proposals.patch');
    Route::patch('leads/{lead}/pipeline/quotations/{quotation}', [LeadQuotationController::class, 'update'])
        ->middleware('permission:edit_quotations|manage_leads')
        ->name('leads.pipeline.quotations.patch');
    Route::patch('leads/{lead}/pipeline/contracts/{contract}', [LeadContractController::class, 'update'])
        ->middleware('permission:edit_contracts|manage_contracts')
        ->name('leads.pipeline.contracts.patch');
    Route::post('leads/{lead}/pipeline/contracts/{contract}/sign', [LeadContractController::class, 'sign'])
        ->middleware('permission:sign_contracts|manage_contracts')
        ->name('leads.pipeline.contracts.sign');
});
