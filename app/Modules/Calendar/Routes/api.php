<?php

use App\Modules\Calendar\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'locale'])->group(function () {

    // ── Master Calendar Aggregator ────────────────────────────────────────────
    Route::middleware('permission:view_calendar')->group(function () {

        // Aggregated feed (Events + ProjectTasks + Invoices)
        Route::get('calendar', [CalendarController::class, 'index'])
            ->name('calendar.index');

        // Calendar Event CRUD
        Route::post('calendar/events', [CalendarController::class, 'store'])
            ->name('calendar.events.store');

        Route::get('calendar/events/{event}', [CalendarController::class, 'show'])
            ->name('calendar.events.show');

        Route::put('calendar/events/{event}', [CalendarController::class, 'update'])
            ->name('calendar.events.update');

        Route::patch('calendar/events/{event}', [CalendarController::class, 'update'])
            ->name('calendar.events.patch');

        Route::delete('calendar/events/{event}', [CalendarController::class, 'destroy'])
            ->name('calendar.events.destroy');
    });
});
