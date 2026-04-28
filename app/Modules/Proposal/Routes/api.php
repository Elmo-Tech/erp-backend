<?php

use App\Modules\Proposal\Controllers\ProposalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('proposals', ProposalController::class);
    Route::post('proposals/fast-track', [ProposalController::class, 'fastTrack'])->name('proposals.fast-track');
    Route::post('proposals/{proposal}/upload', [ProposalController::class, 'uploadFile'])->name('proposals.upload');
    Route::post('proposals/{proposal}/send', [ProposalController::class, 'send'])->name('proposals.send');
    Route::post('proposals/{proposal}/approve', [ProposalController::class, 'approve'])->name('proposals.approve');
    Route::post('proposals/{proposal}/reject', [ProposalController::class, 'reject'])->name('proposals.reject');
    Route::get('proposals-expiring', [ProposalController::class, 'expiring'])->name('proposals.expiring');
});
