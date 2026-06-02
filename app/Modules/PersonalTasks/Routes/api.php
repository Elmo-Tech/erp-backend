<?php

use App\Modules\PersonalTasks\Controllers\PersonalTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('personal-tasks/time-entries/active', [PersonalTaskController::class, 'active'])
        ->name('personal-tasks.time-entries.active');

    Route::get('personal-tasks', [PersonalTaskController::class, 'index'])
        ->name('personal-tasks.index');

    Route::post('personal-tasks', [PersonalTaskController::class, 'store'])
        ->name('personal-tasks.store');

    Route::patch('personal-tasks/{task}', [PersonalTaskController::class, 'update'])
        ->name('personal-tasks.update');

    Route::delete('personal-tasks/{task}', [PersonalTaskController::class, 'destroy'])
        ->name('personal-tasks.destroy');

    Route::post('personal-tasks/{task}/complete', [PersonalTaskController::class, 'complete'])
        ->name('personal-tasks.complete');

    Route::post('personal-tasks/{task}/time-entries/toggle', [PersonalTaskController::class, 'toggleTimeEntry'])
        ->name('personal-tasks.time-entries.toggle');

    Route::post('personal-tasks/{task}/time-entries', [PersonalTaskController::class, 'storeTimeEntry'])
        ->name('personal-tasks.time-entries.store');

    Route::patch('personal-tasks/{task}/time-entries/{entry}', [PersonalTaskController::class, 'updateTimeEntry'])
        ->name('personal-tasks.time-entries.update');

    Route::delete('personal-tasks/{task}/time-entries/{entry}', [PersonalTaskController::class, 'destroyTimeEntry'])
        ->name('personal-tasks.time-entries.destroy');
});
