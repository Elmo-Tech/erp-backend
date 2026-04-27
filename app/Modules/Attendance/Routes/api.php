<?php

use App\Modules\Attendance\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/attendance')->group(function () {
    Route::get('today', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::get('history', [AttendanceController::class, 'history'])->name('attendance.history');
    Route::get('summary', [AttendanceController::class, 'summary'])->name('attendance.summary');
    Route::post('clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::get('all', [AttendanceController::class, 'all'])->name('attendance.all');
    Route::post('/', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('{id}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::post('{id}/deduction', [AttendanceController::class, 'addDeduction'])->name('attendance.add-deduction');
    Route::post('{id}/grace-day', [AttendanceController::class, 'markGraceDay'])->name('attendance.mark-grace-day');
    Route::delete('{id}/grace-day', [AttendanceController::class, 'removeGraceDay'])->name('attendance.remove-grace-day');
});
