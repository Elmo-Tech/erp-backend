<?php

use App\Modules\Lead\Controllers\LeadController;
use App\Modules\Lead\Controllers\LeadConversionController;
use App\Modules\Lead\Controllers\LeadHistoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('leads', LeadController::class);
    Route::get('leads-kanban', [LeadController::class, 'kanban'])->name('leads.kanban');
    Route::get('leads-follow-ups', [LeadController::class, 'followUps'])->name('leads.follow-ups');
    Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.update-status');
    Route::get('leads/{lead}/activities', [LeadController::class, 'activities'])->name('leads.activities');
    Route::post('leads/{lead}/activities', [LeadController::class, 'addActivity'])->name('leads.add-activity');
    Route::get('leads/{lead}/meetings', [LeadController::class, 'meetings'])->name('leads.meetings');
    Route::post('leads/{lead}/meetings', [LeadController::class, 'scheduleMeeting'])->name('leads.schedule-meeting');
    Route::put('leads/{lead}/meetings/{meeting}', [LeadController::class, 'updateMeeting'])->name('leads.update-meeting');

    Route::get('leads/{lead}/can-convert', [LeadConversionController::class, 'canConvert'])->name('leads.can-convert');
    Route::post('leads/{lead}/convert', [LeadConversionController::class, 'convert'])->name('leads.convert');

    Route::get('leads/{lead}/history', [LeadHistoryController::class, 'leadHistory'])->name('leads.history');
    Route::get('leads/{lead}/full-history', [LeadHistoryController::class, 'leadFullHistory'])->name('leads.full-history');
    Route::get('leads/{lead}/history-stats', [LeadHistoryController::class, 'leadHistoryStats'])->name('leads.history-stats');
    Route::get('proposals/{proposal}/history', [LeadHistoryController::class, 'proposalHistory'])->name('proposals.history');
    Route::get('quotations/{quotation}/history', [LeadHistoryController::class, 'quotationHistory'])->name('quotations.history');
    Route::get('audit/recent', [LeadHistoryController::class, 'recentActivity'])->name('audit.recent');
    Route::get('audit/user/{userId}', [LeadHistoryController::class, 'userActivity'])->name('audit.user');
});
