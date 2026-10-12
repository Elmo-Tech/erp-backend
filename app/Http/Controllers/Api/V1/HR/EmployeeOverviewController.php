<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceSettlement;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeePermissionAllowance;
use App\Models\EmployeeShortPermission;
use App\Models\LeaveType;
use App\Models\SalaryAdjustment;
use App\Models\TimeEntry;
use App\Policies\UserAdministrationPolicy;
use App\Services\HR\EmployeeService;
use App\Services\HR\LeaveService;
use App\Services\HR\OvertimeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmployeeOverviewController extends Controller
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $employee = app(EmployeeService::class)->getEmployee($id);
        abort_if($employee === null, 404, 'Employee not found.');
        app(UserAdministrationPolicy::class)->authorizeHrView($request->user(), $employee, 'manage_employees');
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $period = Carbon::createFromFormat('!Y-m', $validated['month']);
        $start = $period->copy()->startOfMonth()->toDateString();
        $end = $period->copy()->endOfMonth()->toDateString();

        $attendance = Attendance::where('user_id', $id)->whereBetween('date', [$start, $end])->get();
        $adjustments = SalaryAdjustment::where('user_id', $id)
            ->forPayrollPeriod($period->year, $period->month)
            ->whereIn('status', [SalaryAdjustment::STATUS_APPROVED, SalaryAdjustment::STATUS_PROCESSED])->get();
        $advances = EmployeeAdvance::with('settlements')->where('employee_id', $id)->get();
        $allowance = EmployeePermissionAllowance::where('employee_id', $id)->where('period', $validated['month'])->first();
        [$allocatedMinutes] = app(LeaveService::class)->monthlyPaidPermissionMinutes();
        $usedMinutes = $allowance?->used_paid_minutes ?? 0;
        $allocatedMinutes = $allowance?->allocated_minutes ?? $allocatedMinutes;
        $permissions = EmployeeShortPermission::where('employee_id', $id)->whereBetween('permission_date', [$start, $end])
            ->where('status', EmployeeShortPermission::STATUS_APPROVED)->get();
        $balances = EmployeeLeaveBalance::where('employee_id', $id)->where('year', $period->year)->get()->keyBy('leave_type_id');
        $leaveBalances = LeaveType::where('is_paid', true)->where(function ($query) use ($balances): void {
            $query->where('is_active', true)->orWhereIn('id', $balances->keys());
        })->get()->map(function (LeaveType $type) use ($balances): array {
            $balance = $balances->get($type->id);
            $allocated = (float) ($balance?->allocated_days ?? $type->annual_allowance_days);
            $used = (float) ($balance?->used_days ?? 0);

            return ['name' => $type->name, 'allocatedDays' => $allocated, 'usedDays' => $used, 'remainingDays' => max(0, $allocated - $used)];
        });
        $deductions = (float) $adjustments->whereIn('adjustment_type', [SalaryAdjustment::TYPE_DEDUCTION, SalaryAdjustment::TYPE_PENALTY])->sum('amount');

        return response()->json(['data' => [
            'month' => $validated['month'],
            'salary' => $employee->salary === null ? null : (float) $employee->salary,
            'deductions' => $deductions,
            'additions' => (float) $adjustments->whereIn('adjustment_type', [SalaryAdjustment::TYPE_OVERTIME, SalaryAdjustment::TYPE_REWARD, SalaryAdjustment::TYPE_BONUS, SalaryAdjustment::TYPE_ALLOWANCE])->sum('amount'),
            'advanceRecovery' => (float) $adjustments->where('adjustment_type', SalaryAdjustment::TYPE_ADVANCE_RECOVERY)->sum('amount'),
            'advancesDisbursed' => (float) $advances->filter(fn ($advance) => $advance->disbursed_at?->format('Y-m') === $validated['month'])->sum('disbursed_amount'),
            'advancesOutstanding' => (float) $advances->sum(fn ($advance) => $advance->outstanding_amount),
            'scheduledRecovery' => (float) EmployeeAdvanceSettlement::whereHas('advance', fn ($query) => $query->where('employee_id', $id))
                ->whereBetween('due_on', [$start, $end])->where('status', EmployeeAdvanceSettlement::STATUS_SCHEDULED)->sum('scheduled_amount'),
            'attendance' => [
                'days' => $attendance->count(),
                'lateDays' => $attendance->where('late_minutes', '>', 0)->count(),
                'lateMinutes' => (int) $attendance->sum('late_minutes'),
                'workedMinutes' => (int) TimeEntry::where('user_id', $id)->whereYear('start_time', $period->year)
                    ->whereMonth('start_time', $period->month)->whereNotNull('end_time')->sum('duration_minutes'),
            ],
            'leaveDays' => (float) EmployeeLeave::where('employee_id', $id)->where('status', EmployeeLeave::STATUS_APPROVED)
                ->whereBetween('start_date', [$start, $end])->sum('working_days'),
            'leaveBalances' => $leaveBalances->values(),
            'permissions' => [
                'count' => $permissions->count(),
                'totalMinutes' => (int) $permissions->sum('duration_minutes'),
                'paidUsedMinutes' => $usedMinutes,
                'allocatedMinutes' => $allocatedMinutes,
                'remainingMinutes' => max(0, $allocatedMinutes - $usedMinutes),
                'overageMinutes' => max(0, $usedMinutes - $allocatedMinutes),
            ],
            'overtime' => app(OvertimeRequestService::class)->periodSummary($employee, $period->year, $period->month),
        ]]);
    }
}
