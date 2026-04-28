<?php

use App\Modules\Activity\Controllers\ActivityController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('activities', ActivityController::class);
    Route::post('activities/{activity}/complete', [ActivityController::class, 'complete'])->name('activities.complete');
    Route::post('activities/{activity}/cancel', [ActivityController::class, 'cancel'])->name('activities.cancel');

    Route::get('meetings', [ActivityController::class, 'meetings'])->name('meetings.index');
    Route::post('meetings/{meeting}/complete', [ActivityController::class, 'completeMeeting'])->name('meetings.complete');
    Route::post('meetings/{meeting}/cancel', [ActivityController::class, 'cancelMeeting'])->name('meetings.cancel');

    Route::get('schedule/today', [ActivityController::class, 'todaySchedule'])->name('schedule.today');
    Route::get('schedule/upcoming', [ActivityController::class, 'upcomingSchedule'])->name('schedule.upcoming');
});
