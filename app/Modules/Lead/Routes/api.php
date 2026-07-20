<?php

use App\Modules\Lead\Controllers\LeadContractController;
use App\Modules\Lead\Controllers\LeadController;
use App\Modules\Lead\Controllers\LeadConversionController;
use App\Modules\Lead\Controllers\LeadPipelineController;
use App\Modules\Lead\Controllers\LeadProposalController;
use App\Modules\Lead\Controllers\LeadQuotationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'locale'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Lead Pipeline Routes
    |--------------------------------------------------------------------------
    |
    | view_leads   — read-only access (list, show)
    | manage_leads — write access (create, stage updates, conversion, delete)
    |
    | The {lead} segment uses Laravel's implicit route model binding.
    |
    */

    // ── Read-only — requires view_leads ──────────────────────────────────────
    Route::middleware('permission:view_leads')->group(function () {
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');

        // Sub-resource lists (read-only)
        Route::get('leads/{lead}/proposals', [LeadProposalController::class,  'index'])->name('leads.proposals.index');
        Route::get('leads/{lead}/quotations', [LeadQuotationController::class, 'index'])->name('leads.quotations.index');
        Route::get('leads/{lead}/contracts', [LeadContractController::class,  'index'])->name('leads.contracts.index');
    });

    // ── Write — requires manage_leads ─────────────────────────────────────────
    Route::middleware('permission:manage_leads')->group(function () {
        // Create a new lead (drops into the "new" column)
        Route::post('leads', [LeadController::class, 'store'])
            ->name('leads.store');

        // Advance a lead to a new pipeline stage (drag-and-drop commit)
        Route::post('leads/{lead}/stage', [LeadPipelineController::class, 'updateStage'])
            ->name('leads.stage.update');

        // Convert a won lead into a Client + Project
        Route::post('leads/{lead}/convert', [LeadConversionController::class, 'convert'])
            ->name('leads.convert');

        // ── Direct creation from the Lead Details Page ────────────────────────
        // These endpoints accept the EXACT same payload shape as the pipeline's
        // stage_results[*] entries, so the frontend reuses the same form tabs
        // and payload mapper without any changes.
        Route::post('leads/{lead}/proposals', [LeadProposalController::class,  'store'])->name('leads.proposals.store');
        Route::post('leads/{lead}/quotations', [LeadQuotationController::class, 'store'])->name('leads.quotations.store');
        Route::post('leads/{lead}/contracts', [LeadContractController::class,  'store'])->name('leads.contracts.store');

        Route::patch('leads/{lead}/proposals/{proposal}', [LeadProposalController::class, 'update'])->name('leads.proposals.patch');
        Route::patch('leads/{lead}/quotations/{quotation}', [LeadQuotationController::class, 'update'])->name('leads.quotations.patch');
        Route::patch('leads/{lead}/contracts/{contract}', [LeadContractController::class, 'update'])->name('leads.contracts.patch');
    });
});
