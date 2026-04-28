<?php

use App\Modules\Audit\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin/audit-logs')->group(function () {
    Route::get('/', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('action-types', [AuditLogController::class, 'actionTypes'])->name('admin.audit-logs.action-types');
    Route::get('stats', [AuditLogController::class, 'stats'])->name('admin.audit-logs.stats');
    Route::get('{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');
});
