<?php

use App\Modules\Meetings\Controllers\MeetingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'locale'])->group(function () {

    // ── Read routes ───────────────────────────────────────────────────────────
    Route::middleware('permission:view_meetings|schedule_meetings')->group(function () {
        Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
        Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
        Route::get('schedule/today', [MeetingController::class, 'todaySchedule'])->name('schedule.today');
        Route::get('schedule/upcoming', [MeetingController::class, 'upcomingSchedule'])->name('schedule.upcoming');
    });

    // ── Write routes ──────────────────────────────────────────────────────────
    Route::middleware('permission:schedule_meetings')->group(function () {
        Route::post('activities', [MeetingController::class, 'store'])->name('activities.store');
        Route::post('meetings', [MeetingController::class, 'store'])->name('meetings.store');
        Route::put('meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
        Route::patch('meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.patch');
        Route::delete('meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
        Route::post('meetings/{meeting}/complete', [MeetingController::class, 'complete'])->name('meetings.complete');
        Route::post('meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');
    });
});
