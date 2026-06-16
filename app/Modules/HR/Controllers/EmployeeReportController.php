<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\PayrollTransaction;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Employee 360 Report
 *
 * GET /api/v1/hr/employee-report/{id}?month=5&year=2026
 *
 * Returns a comprehensive breakdown of an employee's attendance, points,
 * deliverables, and financial summary for a given calendar month.
 *
 * Permission: manage_employees
 */
class EmployeeReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_if(! $request->user()->can('manage_employees'), 403, 'Unauthorized');

        $user = User::with(['roles:id,name', 'employeeProfile'])->findOrFail($id);

        $month = (int) $request->query('month', now()->month);
        $year  = (int) $request->query('year', now()->year);

        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd   = $periodStart->copy()->endOfMonth();

        // ── Attendance ────────────────────────────────────────────────────────
        $attendanceRecords = Attendance::where('user_id', $id)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('date')
            ->get();

        $attendanceDays = $attendanceRecords->map(fn ($a) => [
            'date'             => $a->date instanceof \Illuminate\Support\Carbon
                ? $a->date->toDateString()
                : (string) $a->date,
            'dayLabel'         => Carbon::parse($a->date)->format('D d'),
            'clockIn'          => $a->clock_in ? Carbon::parse($a->clock_in)->format('H:i') : null,
            'clockOut'         => $a->clock_out ? Carbon::parse($a->clock_out)->format('H:i') : null,
            'workedHours'      => $a->worked_hours ?? 0,
            'lateMinutes'      => $a->late_minutes ?? 0,
            'status'           => $a->status,
            'usedGraceDay'     => (bool) ($a->used_grace_day ?? false),
            'deductionMinutes' => $a->deduction_minutes ?? 0,
        ])->values()->toArray();

        $lateDays   = $attendanceRecords->where('status', 'late')->count();
        $absentDays = $attendanceRecords->where('status', 'absent')->count();
        $presentDays = $attendanceRecords->whereIn('status', ['present', 'late'])->count();

        // ── Point Ledger ──────────────────────────────────────────────────────
        $pointEntries = PointTransaction::where('user_id', $id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderBy('created_at')
            ->get();

        $pointLedger = $pointEntries->map(fn ($p) => [
            'id'         => $p->id,
            'date'       => $p->created_at->toDateString(),
            'points'     => $p->points,
            'reason'     => $p->reason ?? '',
            'sourceType' => $p->source_type ?? null,
        ])->values()->toArray();

        $pointsBalance = (int) $pointEntries->sum('points');

        // ── Tasks / Deliverables ──────────────────────────────────────────────
        $projectTasks = ProjectTask::query()
            ->where('assignee_id', $id)
            ->whereBetween('due_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->map(fn (ProjectTask $task): array => [
                'date' => $task->due_date?->toDateString(),
                'completed' => $task->status === ExecutionTaskStatus::DONE,
                'estimated_hours' => (float) ($task->estimated_hours ?? 0),
            ]);

        $personalTasks = PersonalTask::query()
            ->where('user_id', $id)
            ->whereBetween('due_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->map(fn (PersonalTask $task): array => [
                'date' => $task->due_date?->toDateString(),
                'completed' => $task->status === 'done',
                'estimated_hours' => round(((int) ($task->estimated_minutes ?? 0)) / 60, 2),
            ]);

        $tasks = $projectTasks
            ->concat($personalTasks)
            ->filter(fn (array $task): bool => $task['date'] !== null)
            ->values();

        $tasksByDate = $tasks->groupBy('date');

        $deliverableDays = $tasksByDate->map(fn ($dayTasks, $date) => [
            'date'               => $date,
            'submitted'          => $dayTasks->where('completed', true)->count() > 0,
            'taskCount'          => $dayTasks->count(),
            'completedTaskCount' => $dayTasks->where('completed', true)->count(),
            'qaIssues'           => [],
        ])->values()->toArray();

        $totalTaskHours      = $tasks->sum('estimated_hours') ?? 0;
        $completedTaskCount  = $tasks->where('completed', true)->count();

        // ── Manual Payroll Transactions ───────────────────────────────────────
        $manualTransactions = PayrollTransaction::where('user_id', $id)
            ->whereBetween('transaction_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('transaction_date')
            ->get();

        $manualBonusTotal     = (float) $manualTransactions->where('type', 'manual_bonus')->sum('amount');
        $manualDeductionTotal = (float) $manualTransactions->where('type', 'manual_deduction')->sum('amount');

        $manualTransactionsData = $manualTransactions->map(fn ($t) => [
            'id'              => $t->id,
            'userId'          => $t->user_id,
            'amount'          => (float) $t->amount,
            'type'            => $t->type,
            'reason'          => $t->reason,
            'transactionDate' => $t->transaction_date instanceof \Illuminate\Support\Carbon
                ? $t->transaction_date->toDateString()
                : (string) $t->transaction_date,
            'createdAt'       => $t->created_at?->toISOString() ?? '',
            'updatedAt'       => $t->updated_at?->toISOString() ?? '',
        ])->values()->toArray();

        // ── Financial Breakdown ───────────────────────────────────────────────
        $profile    = $user->employeeProfile;
        $baseSalary = $profile ? (float) $profile->base_salary : 0.0;
        $minuteRate = ($profile && $profile->hourly_rate > 0)
            ? (float) $profile->hourly_rate / 60
            : 0.0;

        $pointValue        = (float) Setting::getValue('gamification_point_value', 50);
        $pointsBonusAmount = max(0.0, round($pointsBalance * $pointValue, 2));

        $latenessMinutes         = (int) $attendanceRecords->where('status', 'late')->sum('deduction_minutes');
        $latenessDeductionAmount = round($latenessMinutes * $minuteRate, 2);

        // Unpaid leave deduction
        $unpaidLeaves = LeaveRequest::where('user_id', $id)
            ->where('type', 'unpaid')
            ->where('status', 'approved')
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhereBetween('end_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
            })
            ->get();

        $workingDaysPerMonth = $profile?->working_days_per_week
            ? round(($profile->working_days_per_week / 7) * $periodStart->daysInMonth)
            : 22;

        $dailyRate              = $workingDaysPerMonth > 0 ? $baseSalary / $workingDaysPerMonth : 0;
        $unpaidLeaveDays        = $unpaidLeaves->sum(fn ($l) => $l->durationInDays());
        $unpaidLeaveDeduction   = round($unpaidLeaveDays * $dailyRate, 2);

        $netSalary = round(
            $baseSalary
            + $pointsBonusAmount
            + $manualBonusTotal
            - $latenessDeductionAmount
            - $manualDeductionTotal
            - $unpaidLeaveDeduction,
            2
        );

        // Payslip status for this period
        $payslip = Payslip::where('user_id', $id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $financialBreakdown = [
            'baseSalary'              => $baseSalary,
            'pointsBonusAmount'       => $pointsBonusAmount,
            'manualBonusTotal'        => $manualBonusTotal,
            'latenessDeductionAmount' => $latenessDeductionAmount,
            'manualDeductionTotal'    => $manualDeductionTotal,
            'unpaidLeaveDeduction'    => $unpaidLeaveDeduction,
            'netSalary'               => $netSalary,
            'payslipStatus'           => $payslip?->status,
        ];

        // ── Summary ───────────────────────────────────────────────────────────
        $summary = [
            'totalTaskHours'     => (float) $totalTaskHours,
            'completedTaskCount' => $completedTaskCount,
            'pointsBalance'      => $pointsBalance,
            'lateDays'           => $lateDays,
            'absentDays'         => $absentDays,
            'presentDays'        => $presentDays,
            'netSalary'          => $netSalary,
        ];

        return ApiResponse::success([
            'employee' => [
                'id'     => $user->id,
                'name'   => $user->name ?? '',
                'email'  => $user->email ?? '',
                'avatar' => $user->avatar,
                'roles'  => $user->roles->pluck('name')->toArray(),
            ],
            'period' => [
                'month' => $month,
                'year'  => $year,
                'label' => Carbon::create($year, $month, 1)->format('F Y'),
            ],
            'summary'             => $summary,
            'attendanceDays'      => $attendanceDays,
            'pointLedger'         => $pointLedger,
            'deliverableDays'     => $deliverableDays,
            'financialBreakdown'  => $financialBreakdown,
            'manualTransactions'  => $manualTransactionsData,
        ], 'Employee report retrieved successfully');
    }
}
