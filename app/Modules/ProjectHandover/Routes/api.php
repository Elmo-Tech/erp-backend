<?php

use App\Modules\ProjectHandover\Controllers\ChangeRequestController;
use App\Modules\ProjectHandover\Controllers\CodeReviewController;
use App\Modules\ProjectHandover\Controllers\HandoverController;
use App\Modules\ProjectHandover\Controllers\InnovationLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('projects/{project}/change-requests', [ChangeRequestController::class, 'index'])->name('change-requests.index');
    Route::post('projects/{project}/change-requests', [ChangeRequestController::class, 'store'])->name('change-requests.store');
    Route::get('projects/{project}/change-requests/summary', [ChangeRequestController::class, 'summary'])->name('change-requests.summary');
    Route::get('change-requests/{changeRequest}', [ChangeRequestController::class, 'show'])->name('change-requests.show');
    Route::put('change-requests/{changeRequest}', [ChangeRequestController::class, 'update'])->name('change-requests.update');
    Route::post('change-requests/{changeRequest}/submit', [ChangeRequestController::class, 'submit'])->name('change-requests.submit');
    Route::post('change-requests/{changeRequest}/approve', [ChangeRequestController::class, 'approve'])->name('change-requests.approve');
    Route::post('change-requests/{changeRequest}/reject', [ChangeRequestController::class, 'reject'])->name('change-requests.reject');
    Route::post('change-requests/{changeRequest}/implement', [ChangeRequestController::class, 'implement'])->name('change-requests.implement');

    Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
    Route::get('handovers/pending', [HandoverController::class, 'pending'])->name('handovers.pending');
    Route::post('projects/{project}/handover', [HandoverController::class, 'store'])->name('handovers.store');
    Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
    Route::put('handovers/{handover}', [HandoverController::class, 'update'])->name('handovers.update');
    Route::post('handovers/{handover}/complete', [HandoverController::class, 'complete'])->name('handovers.complete');
    Route::post('handovers/{handover}/dispute', [HandoverController::class, 'dispute'])->name('handovers.dispute');

    Route::prefix('code-reviews')->group(function () {
        Route::get('/', [CodeReviewController::class, 'index'])->name('code-reviews.index');
        Route::post('/', [CodeReviewController::class, 'store'])->name('code-reviews.store');
        Route::get('/my-pending', [CodeReviewController::class, 'myPending'])->name('code-reviews.my-pending');
        Route::get('/my-stats', [CodeReviewController::class, 'myStats'])->name('code-reviews.my-stats');
        Route::get('/{id}', [CodeReviewController::class, 'show'])->name('code-reviews.show');
        Route::put('/{id}', [CodeReviewController::class, 'update'])->name('code-reviews.update');
        Route::post('/{id}/approve', [CodeReviewController::class, 'approve'])->name('code-reviews.approve');
        Route::post('/{id}/request-revision', [CodeReviewController::class, 'requestRevision'])->name('code-reviews.request-revision');
        Route::post('/{id}/reject', [CodeReviewController::class, 'reject'])->name('code-reviews.reject');
    });

    Route::prefix('innovation-logs')->group(function () {
        Route::get('/', [InnovationLogController::class, 'index'])->name('innovation-logs.index');
        Route::post('/', [InnovationLogController::class, 'store'])->name('innovation-logs.store');
        Route::get('/pending', [InnovationLogController::class, 'pending'])->name('innovation-logs.pending');
        Route::get('/my-summary', [InnovationLogController::class, 'mySummary'])->name('innovation-logs.my-summary');
        Route::get('/{id}', [InnovationLogController::class, 'show'])->name('innovation-logs.show');
        Route::post('/{id}/verify', [InnovationLogController::class, 'verify'])->name('innovation-logs.verify');
        Route::post('/{id}/reject', [InnovationLogController::class, 'reject'])->name('innovation-logs.reject');
        Route::delete('/{id}', [InnovationLogController::class, 'destroy'])->name('innovation-logs.destroy');
    });
});
