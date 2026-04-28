<?php

use App\Modules\HR\Controllers\DeductionTypeController;
use App\Modules\HR\Controllers\EmployeeDeductionController;
use App\Modules\HR\Controllers\EmployeeRewardController;
use App\Modules\HR\Controllers\RewardTypeController;
use App\Modules\HR\Controllers\SalaryAdjustmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hr')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::apiResource('deduction-types', DeductionTypeController::class);
    Route::apiResource('reward-types', RewardTypeController::class);
    Route::post('reward-types/{reward_type}/calculate', [RewardTypeController::class, 'calculate'])->name('hr.reward-types.calculate');

    Route::apiResource('employee-deductions', EmployeeDeductionController::class);
    Route::post('employee-deductions/{employeeDeduction}/approve', [EmployeeDeductionController::class, 'approve'])->name('hr.employee-deductions.approve');
    Route::post('employee-deductions/{employeeDeduction}/reject', [EmployeeDeductionController::class, 'reject'])->name('hr.employee-deductions.reject');
    Route::get('employee-deductions-summary', [EmployeeDeductionController::class, 'summary'])->name('hr.employee-deductions.summary');
    Route::post('employee-deductions/bulk-from-attendance', [EmployeeDeductionController::class, 'bulkFromAttendance'])->name('hr.employee-deductions.bulk-from-attendance');

    Route::apiResource('employee-rewards', EmployeeRewardController::class);
    Route::post('employee-rewards/{employeeReward}/approve', [EmployeeRewardController::class, 'approve'])->name('hr.employee-rewards.approve');
    Route::post('employee-rewards/{employeeReward}/reject', [EmployeeRewardController::class, 'reject'])->name('hr.employee-rewards.reject');
    Route::post('employee-rewards/{employeeReward}/mark-paid', [EmployeeRewardController::class, 'markAsPaid'])->name('hr.employee-rewards.mark-paid');
    Route::get('employee-rewards-summary', [EmployeeRewardController::class, 'summary'])->name('hr.employee-rewards.summary');
    Route::post('employee-rewards/from-points', [EmployeeRewardController::class, 'fromPoints'])->name('hr.employee-rewards.from-points');
    Route::post('employee-rewards/bulk-from-evaluation', [EmployeeRewardController::class, 'bulkFromEvaluation'])->name('hr.employee-rewards.bulk-from-evaluation');

    Route::get('salary-adjustments', [SalaryAdjustmentController::class, 'index'])->name('hr.salary-adjustments.index');
    Route::get('salary-adjustments/{salaryAdjustment}', [SalaryAdjustmentController::class, 'show'])->name('hr.salary-adjustments.show');
    Route::get('salary-adjustments-payroll-summary', [SalaryAdjustmentController::class, 'payrollSummary'])->name('hr.salary-adjustments.payroll-summary');
});
