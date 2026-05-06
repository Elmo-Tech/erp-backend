<?php

use App\Modules\HR\Controllers\AttendanceController;
use App\Modules\HR\Controllers\EmployeeProfileController;
use App\Modules\HR\Controllers\PayslipController;
use App\Modules\HR\Controllers\PointTransactionController;
use App\Modules\HR\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'locale'])->group(function () {

    // ── Shifts (admin only — guarded inside controller via manage_shifts) ─────
    Route::apiResource('hr/shifts', ShiftController::class);

    // ── Employee Profiles (admin only — guarded via manage_employees) ─────────
    Route::apiResource('hr/employee-profiles', EmployeeProfileController::class);

    // ── Attendance ────────────────────────────────────────────────────────────

    // Any authenticated user can view their own records or today's status
    Route::get('hr/attendance', [AttendanceController::class, 'index'])
        ->name('hr.attendance.index');

    Route::get('hr/attendance/today', [AttendanceController::class, 'today'])
        ->name('hr.attendance.today');

    // Self check-out — any authenticated employee
    Route::post('hr/attendance/check-out', [AttendanceController::class, 'checkOut'])
        ->name('hr.attendance.check-out');

    // Admin override — guarded inside controller via manage_attendance
    Route::patch('hr/attendance/{attendance}', [AttendanceController::class, 'update'])
        ->name('hr.attendance.update');

    // ── Payroll / Payslips ────────────────────────────────────────────────────
    // manage_payroll  → HR admins: full CRUD + generate + approve + mark-paid
    // view_payroll    → Employees: read-only access to their own payslips

    // Manual generation endpoint (HR admin only — guarded inside controller)
    Route::post('hr/payslips/generate', [PayslipController::class, 'generate'])
        ->name('hr.payslips.generate');

    // Lifecycle transitions (HR admin only)
    Route::patch('hr/payslips/{payslip}/approve', [PayslipController::class, 'approve'])
        ->name('hr.payslips.approve');

    Route::patch('hr/payslips/{payslip}/mark-paid', [PayslipController::class, 'markPaid'])
        ->name('hr.payslips.mark-paid');

    // Standard CRUD (index + show + destroy)
    Route::get('hr/payslips', [PayslipController::class, 'index'])
        ->name('hr.payslips.index');

    Route::get('hr/payslips/{payslip}', [PayslipController::class, 'show'])
        ->name('hr.payslips.show');

    Route::delete('hr/payslips/{payslip}', [PayslipController::class, 'destroy'])
        ->name('hr.payslips.destroy');

    // ── Gamification — Employee point history ─────────────────────────────────
    Route::get('hr/my-points', [PointTransactionController::class, 'myPoints'])
        ->name('hr.my-points.index');
});
