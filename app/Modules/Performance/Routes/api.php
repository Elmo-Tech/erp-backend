<?php

use App\Modules\Performance\Controllers\PerformanceEvaluationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/performance')->group(function () {
    Route::get('periods', [PerformanceEvaluationController::class, 'periods'])->name('performance.periods.index');
    Route::post('periods', [PerformanceEvaluationController::class, 'createPeriod'])->name('performance.periods.store');
    Route::post('periods/{periodId}/activate', [PerformanceEvaluationController::class, 'activatePeriod'])->name('performance.periods.activate');
    Route::get('periods/{periodId}/bonuses', [PerformanceEvaluationController::class, 'periodBonuses'])->name('performance.periods.bonuses');

    Route::get('evaluations', [PerformanceEvaluationController::class, 'index'])->name('performance.evaluations.index');
    Route::get('evaluations/{id}', [PerformanceEvaluationController::class, 'show'])->name('performance.evaluations.show');
    Route::put('evaluations/{id}', [PerformanceEvaluationController::class, 'update'])->name('performance.evaluations.update');
    Route::post('evaluations/{id}/calculate', [PerformanceEvaluationController::class, 'calculate'])->name('performance.evaluations.calculate');
    Route::post('evaluations/{id}/approve', [PerformanceEvaluationController::class, 'approve'])->name('performance.evaluations.approve');
    Route::get('my-evaluations', [PerformanceEvaluationController::class, 'myEvaluations'])->name('performance.my-evaluations');
});
