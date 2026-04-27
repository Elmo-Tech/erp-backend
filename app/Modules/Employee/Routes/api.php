<?php

use App\Modules\Employee\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('employees', EmployeeController::class)->except(['destroy']);
    Route::get('employees/{id}/performance', [EmployeeController::class, 'performance'])->name('employees.performance');
    Route::post('employees/{id}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
    Route::post('employees/{id}/activate', [EmployeeController::class, 'activate'])->name('employees.activate');
});
