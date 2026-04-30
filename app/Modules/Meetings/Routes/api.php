<?php

use App\Modules\Meetings\Controllers\MeetingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('activities', [MeetingController::class, 'store'])->name('activities.store');

    Route::apiResource('meetings', MeetingController::class);
    Route::post('meetings/{meeting}/complete', [MeetingController::class, 'complete'])->name('meetings.complete');
    Route::post('meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');

    Route::get('schedule/today', [MeetingController::class, 'todaySchedule'])->name('schedule.today');
    Route::get('schedule/upcoming', [MeetingController::class, 'upcomingSchedule'])->name('schedule.upcoming');
});
