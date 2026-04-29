<?php

use App\Modules\SOP\Controllers\SOPController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::prefix('sop')->group(function () {
        Route::get('directives/today', [SOPController::class, 'today'])->name('sop.directives.today');
        Route::post('daily-checkins', [SOPController::class, 'submit'])->name('sop.daily-checkins.submit');
    });

    Route::prefix('admin/sop')
        ->middleware('permission:manage_sop_directives')
        ->group(function () {
            Route::apiResource('directives', SOPController::class)
                ->names('admin.sop.directives');
        });
});
