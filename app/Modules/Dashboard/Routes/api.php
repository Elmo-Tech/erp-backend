<?php

use App\Modules\Dashboard\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('dashboard/overview', [DashboardController::class, 'index'])->name('dashboard.overview');
    Route::post('dashboard/blockers/{task}/escalate', [DashboardController::class, 'escalateBlocker'])->name('dashboard.blockers.escalate');
    Route::post('dashboard/escalated-blockers/{dependency}/resolve', [DashboardController::class, 'resolveEscalatedBlocker'])->name('dashboard.escalated-blockers.resolve');
});
