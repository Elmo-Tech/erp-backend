<?php

use App\Modules\HR\Controllers\AttendanceController;
use App\Modules\HR\Controllers\EmployeeProfileController;
use App\Modules\HR\Controllers\EmployeeReportController;
use App\Modules\HR\Controllers\LeaveRequestController;
use App\Modules\HR\Controllers\PayrollTransactionController;
use App\Modules\HR\Controllers\PayslipController;
use App\Modules\HR\Controllers\PointTransactionController;
use App\Modules\HR\Controllers\ShiftController;
use App\Modules\HR\Controllers\TeamPulseController;
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

    Route::get('hr/attendance/summary', [AttendanceController::class, 'summary'])
        ->name('hr.attendance.summary');

    Route::get('hr/team-pulse', TeamPulseController::class)
        ->name('hr.team-pulse');

    // Self check-in — any authenticated employee
    Route::post('hr/attendance/check-in', [AttendanceController::class, 'checkIn'])
        ->name('hr.attendance.check-in');

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

    Route::post('hr/point-adjustments', [PointTransactionController::class, 'adjust'])
        ->name('hr.point-adjustments.store');

    // ── Leave Requests (HR admin — guarded via manage_hr) ─────────────────────
    Route::apiResource('hr/leave-requests', LeaveRequestController::class)
        ->names('hr.leave-requests');

    // ── Manual Payroll Transactions (HR admin — guarded via manage_payroll) ───
    Route::apiResource('hr/payroll-transactions', PayrollTransactionController::class)
        ->names('hr.payroll-transactions');

    // ── Employee 360 Report (HR admin — guarded via manage_employees) ─────────
    Route::get('hr/employee-report/{id}', [EmployeeReportController::class, 'show'])
        ->name('hr.employee-report.show');
});
