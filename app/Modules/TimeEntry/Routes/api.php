<?php

use App\Modules\TimeEntry\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/time-entries')->group(function () {
    Route::post('start', [TimeEntryController::class, 'start'])->name('time-entries.start');
    Route::post('stop', [TimeEntryController::class, 'stop'])->name('time-entries.stop');
    Route::get('/', [TimeEntryController::class, 'index'])->name('time-entries.index');
    Route::get('active', [TimeEntryController::class, 'active'])->name('time-entries.active');
    Route::get('active-timers', [TimeEntryController::class, 'activeTimers'])->name('time-entries.active-timers');
    Route::post('{id}/stop-admin', [TimeEntryController::class, 'stopTimerAdmin'])->name('time-entries.stop-admin');
    Route::put('{id}', [TimeEntryController::class, 'update'])->name('time-entries.update');
    Route::get('all', [TimeEntryController::class, 'all'])->name('time-entries.all');
});
