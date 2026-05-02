<?php

use App\Modules\Lead\Controllers\LeadController;
use App\Modules\Lead\Controllers\LeadConversionController;
use App\Modules\Lead\Controllers\LeadPipelineController;
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
        // Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
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
    });
});
