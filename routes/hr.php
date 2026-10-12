<?php

use App\Http\Controllers\Api\V1\HR\DeductionTypeController;
use App\Http\Controllers\Api\V1\HR\DirectEmployeeDeductionController;
use App\Http\Controllers\Api\V1\HR\EmployeeAdvanceController;
use App\Http\Controllers\Api\V1\HR\EmployeeDeductionController;
use App\Http\Controllers\Api\V1\HR\EmployeeDocumentController;
use App\Http\Controllers\Api\V1\HR\EmployeeLateDeductionController;
use App\Http\Controllers\Api\V1\HR\EmployeeOvertimeRequestController;
use App\Http\Controllers\Api\V1\HR\EmployeePartialPayController;
use App\Http\Controllers\Api\V1\HR\EmployeeRewardController;
use App\Http\Controllers\Api\V1\HR\EmployeeShortPermissionController;
use App\Http\Controllers\Api\V1\HR\EmploymentContractController;
use App\Http\Controllers\Api\V1\HR\HrPolicyController;
use App\Http\Controllers\Api\V1\HR\LeaveManagementController;
use App\Http\Controllers\Api\V1\HR\RewardTypeController;
use App\Http\Controllers\Api\V1\HR\SalaryAdjustmentController;
use Illuminate\Support\Facades\Route;

// HR routes are loaded inside the /v1 prefixed, throttled, authenticated group
// from routes/api.php. Authorize actions with per-action HR permissions where
// applicable; employee self-service endpoints still enforce hierarchy checks
// inside their controllers.

Route::middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('employees/{id}/documents', [EmployeeDocumentController::class, 'index'])->name('employees.documents.index');
    Route::post('employees/{id}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::get('employee-documents/{document}/download', [EmployeeDocumentController::class, 'download'])->name('employee-documents.download');
    Route::delete('employee-documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employee-documents.destroy');

    Route::get('deduction-types/active', [DirectEmployeeDeductionController::class, 'activeTypes'])->name('deduction-types.active');
    Route::get('employees/{id}/deductions', [DirectEmployeeDeductionController::class, 'history'])->name('employees.deductions.index');
    Route::post('employees/{id}/deductions', [DirectEmployeeDeductionController::class, 'store'])->name('employees.deductions.store');
    Route::post('employee-deductions/{deduction}/approve', [DirectEmployeeDeductionController::class, 'approve'])->name('employee-deductions.approve');
    Route::post('employee-deductions/{deduction}/reject', [DirectEmployeeDeductionController::class, 'reject'])->name('employee-deductions.reject');
    Route::post('employee-deductions/{deduction}/cancel', [DirectEmployeeDeductionController::class, 'cancel'])->name('employee-deductions.cancel');

    Route::get('leave-types', [LeaveManagementController::class, 'index'])->name('leave-types.index');
    Route::get('leave-types/all', [LeaveManagementController::class, 'indexAll'])->middleware('permission:manage_leave_types')->name('leave-types.all');
    Route::post('leave-types', [LeaveManagementController::class, 'storeType'])->middleware('permission:manage_leave_types')->name('leave-types.store');
    Route::put('leave-types/{leaveType}', [LeaveManagementController::class, 'updateType'])->middleware('permission:manage_leave_types')->name('leave-types.update');

    Route::get('employees/{id}/leaves', [LeaveManagementController::class, 'history'])->name('employees.leaves.index');
    Route::post('employees/{id}/leaves', [LeaveManagementController::class, 'store'])->name('employees.leaves.store');
    Route::put('employee-leaves/{leave}', [LeaveManagementController::class, 'update'])->name('employee-leaves.update');
    Route::post('employee-leaves/{leave}/approve', [LeaveManagementController::class, 'approve'])->name('employee-leaves.approve');
    Route::post('employee-leaves/{leave}/reject', [LeaveManagementController::class, 'reject'])->name('employee-leaves.reject');
    Route::post('employee-leaves/{leave}/cancel', [LeaveManagementController::class, 'cancel'])->name('employee-leaves.cancel');

    Route::get('employees/{id}/short-permissions', [EmployeeShortPermissionController::class, 'history'])->name('employees.short-permissions.index');
    Route::get('employee-short-permissions/pending', [EmployeeShortPermissionController::class, 'pending'])->name('employee-short-permissions.pending');
    Route::post('employees/{id}/short-permissions', [EmployeeShortPermissionController::class, 'store'])->name('employees.short-permissions.store');
    Route::put('employee-short-permissions/{permission}', [EmployeeShortPermissionController::class, 'update'])->name('employee-short-permissions.update');
    Route::post('employee-short-permissions/{permission}/approve', [EmployeeShortPermissionController::class, 'approve'])->name('employee-short-permissions.approve');
    Route::post('employee-short-permissions/{permission}/reject', [EmployeeShortPermissionController::class, 'reject'])->name('employee-short-permissions.reject');
    Route::post('employee-short-permissions/{permission}/cancel', [EmployeeShortPermissionController::class, 'cancel'])->name('employee-short-permissions.cancel');

    Route::get('employees/{id}/overtime-requests', [EmployeeOvertimeRequestController::class, 'history'])->name('employees.overtime-requests.index');
    Route::post('employees/{id}/overtime-requests', [EmployeeOvertimeRequestController::class, 'store'])->name('employees.overtime-requests.store');
    Route::post('employee-overtime-requests/{request}/approve', [EmployeeOvertimeRequestController::class, 'approve'])->name('employee-overtime-requests.approve');
    Route::post('employee-overtime-requests/{request}/reject', [EmployeeOvertimeRequestController::class, 'reject'])->name('employee-overtime-requests.reject');
    Route::post('employee-overtime-requests/{request}/cancel', [EmployeeOvertimeRequestController::class, 'cancel'])->name('employee-overtime-requests.cancel');

    Route::get('employees/{id}/advances', [EmployeeAdvanceController::class, 'history'])->name('employees.advances.index');
    Route::post('employees/{id}/advances', [EmployeeAdvanceController::class, 'store'])->name('employees.advances.store');
    Route::get('employee-advances/{advance}', [EmployeeAdvanceController::class, 'show'])->name('employee-advances.show');
    Route::post('employee-advances/{advance}/approve', [EmployeeAdvanceController::class, 'approve'])->name('employee-advances.approve');
    Route::post('employee-advances/{advance}/reject', [EmployeeAdvanceController::class, 'reject'])->name('employee-advances.reject');
    Route::post('employee-advances/{advance}/cancel', [EmployeeAdvanceController::class, 'cancel'])->name('employee-advances.cancel');
    Route::post('employee-advances/{advance}/disburse', [EmployeeAdvanceController::class, 'disburse'])->name('employee-advances.disburse');
    Route::post('employee-advance-settlements/{settlement}/settle', [EmployeeAdvanceController::class, 'settle'])->name('employee-advance-settlements.settle');

    Route::get('employees/{id}/partial-pays', [EmployeePartialPayController::class, 'history'])->name('employees.partial-pays.index');
    Route::post('employees/{id}/partial-pays/preview', [EmployeePartialPayController::class, 'preview'])->name('employees.partial-pays.preview');
    Route::post('employees/{id}/partial-pays', [EmployeePartialPayController::class, 'store'])->name('employees.partial-pays.store');
    Route::post('employee-partial-pays/{record}/approve', [EmployeePartialPayController::class, 'approve'])->name('employee-partial-pays.approve');
    Route::post('employee-partial-pays/{record}/cancel', [EmployeePartialPayController::class, 'cancel'])->name('employee-partial-pays.cancel');
});

Route::middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('employees/{id}/employment-contracts', [EmploymentContractController::class, 'index'])->name('employees.employment-contracts.index');
    Route::post('employees/{id}/employment-contracts', [EmploymentContractController::class, 'store'])->name('employees.employment-contracts.store');
    Route::put('employment-contracts/{contract}', [EmploymentContractController::class, 'update'])->name('employment-contracts.update');
    Route::delete('employment-contracts/{contract}', [EmploymentContractController::class, 'destroy'])->name('employment-contracts.destroy');
    Route::post('employment-contracts/{contract}/file', [EmploymentContractController::class, 'uploadFile'])->name('employment-contracts.file.upload');
    Route::get('employment-contracts/{contract}/file', [EmploymentContractController::class, 'downloadFile'])->name('employment-contracts.file.download');
    Route::delete('employment-contracts/{contract}/file', [EmploymentContractController::class, 'deleteFile'])->name('employment-contracts.file.destroy');
    Route::get('employees/{id}/late-deductions', [EmployeeLateDeductionController::class, 'index'])->name('employees.late-deductions.index');
    Route::post('employees/{id}/late-deductions/evaluate', [EmployeeLateDeductionController::class, 'evaluate'])->name('employees.late-deductions.evaluate');
    Route::post('employees/{id}/late-deductions/reset', [EmployeeLateDeductionController::class, 'reset'])->name('employees.late-deductions.reset');
    Route::put('employee-late-deductions/{record}/override', [EmployeeLateDeductionController::class, 'override'])->name('employee-late-deductions.override');
});

// HR Deductions & Rewards Routes
Route::prefix('hr')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::middleware('permission:manage_hr_settings')->group(function () {
        Route::get('policies/current', [HrPolicyController::class, 'current'])->name('hr.policies.current');
        Route::get('policies', [HrPolicyController::class, 'index'])->name('hr.policies.index');
        Route::post('policies', [HrPolicyController::class, 'store'])->name('hr.policies.store');
    });

    // Deduction Types Routes
    Route::apiResource('deduction-types', DeductionTypeController::class)->middleware('simplified.disabled:advanced_deductions');

    // Reward Types Routes
    Route::apiResource('reward-types', RewardTypeController::class)->middleware('simplified.disabled:rewards');
    Route::post('reward-types/{reward_type}/calculate', [RewardTypeController::class, 'calculate'])->middleware('simplified.disabled:rewards')->name('hr.reward-types.calculate');

    // Employee Deductions Routes
    Route::apiResource('employee-deductions', EmployeeDeductionController::class)->middleware('simplified.disabled:advanced_deductions');
    Route::post('employee-deductions/{employeeDeduction}/approve', [EmployeeDeductionController::class, 'approve'])->middleware('simplified.disabled:advanced_deductions')->name('hr.employee-deductions.approve');
    Route::post('employee-deductions/{employeeDeduction}/reject', [EmployeeDeductionController::class, 'reject'])->middleware('simplified.disabled:advanced_deductions')->name('hr.employee-deductions.reject');
    Route::get('employee-deductions-summary', [EmployeeDeductionController::class, 'summary'])->middleware('simplified.disabled:advanced_deductions')->name('hr.employee-deductions.summary');
    Route::post('employee-deductions/bulk-from-attendance', [EmployeeDeductionController::class, 'bulkFromAttendance'])->middleware('simplified.disabled:advanced_deductions')->name('hr.employee-deductions.bulk-from-attendance');

    // Employee Rewards Routes
    Route::apiResource('employee-rewards', EmployeeRewardController::class)->middleware('simplified.disabled:rewards');
    Route::post('employee-rewards/{employeeReward}/approve', [EmployeeRewardController::class, 'approve'])->middleware('simplified.disabled:rewards')->name('hr.employee-rewards.approve');
    Route::post('employee-rewards/{employeeReward}/reject', [EmployeeRewardController::class, 'reject'])->middleware('simplified.disabled:rewards')->name('hr.employee-rewards.reject');
    Route::post('employee-rewards/{employeeReward}/mark-paid', [EmployeeRewardController::class, 'markAsPaid'])->middleware('simplified.disabled:rewards')->name('hr.employee-rewards.mark-paid');
    Route::get('employee-rewards-summary', [EmployeeRewardController::class, 'summary'])->middleware('simplified.disabled:rewards')->name('hr.employee-rewards.summary');
    Route::post('employee-rewards/from-points', [EmployeeRewardController::class, 'fromPoints'])->middleware('simplified.disabled:rewards')->name('hr.employee-rewards.from-points');
    Route::post('employee-rewards/bulk-from-evaluation', [EmployeeRewardController::class, 'bulkFromEvaluation'])->middleware('simplified.disabled:rewards')->name('hr.employee-rewards.bulk-from-evaluation');

    // Salary Adjustments Routes
    Route::get('salary-adjustments', [SalaryAdjustmentController::class, 'index'])->middleware('simplified.disabled:salary_adjustments')->name('hr.salary-adjustments.index');
    Route::get('salary-adjustments-payroll-summary', [SalaryAdjustmentController::class, 'payrollSummary'])->middleware('simplified.disabled:salary_adjustments')->name('hr.salary-adjustments.payroll-summary');
    Route::post('salary-adjustments/bulk-approve', [SalaryAdjustmentController::class, 'bulkApprove'])->middleware('simplified.disabled:salary_adjustments')->name('hr.salary-adjustments.bulk-approve');
    Route::post('salary-adjustments/bulk-process', [SalaryAdjustmentController::class, 'bulkProcess'])->middleware('simplified.disabled:salary_adjustments')->name('hr.salary-adjustments.bulk-process');
    Route::post('salary-adjustments/{salaryAdjustment}/approve', [SalaryAdjustmentController::class, 'approve'])->middleware('simplified.disabled:salary_adjustments')->name('hr.salary-adjustments.approve');
    Route::post('salary-adjustments/{salaryAdjustment}/cancel', [SalaryAdjustmentController::class, 'cancel'])->middleware('simplified.disabled:salary_adjustments')->name('hr.salary-adjustments.cancel');
    Route::post('salary-adjustments/{salaryAdjustment}/mark-processed', [SalaryAdjustmentController::class, 'markProcessed'])->middleware('simplified.disabled:salary_adjustments')->name('hr.salary-adjustments.mark-processed');
    Route::get('salary-adjustments/{salaryAdjustment}', [SalaryAdjustmentController::class, 'show'])->middleware('simplified.disabled:salary_adjustments')->name('hr.salary-adjustments.show');
});
