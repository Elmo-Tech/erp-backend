<?php

namespace App\Services\Reporting;

use App\Models\Attendance;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeDocument;
use App\Models\EmployeeLateDeduction;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeOvertimeRequest;
use App\Models\EmployeePartialPay;
use App\Models\EmployeePermissionAllowance;
use App\Models\EmployeeShortPermission;
use App\Models\LeaveType;
use App\Models\PerformanceEvaluation;
use App\Models\Project;
use App\Models\SalaryAdjustment;
use App\Models\Task;
use App\Models\TaskHelpRequest;
use App\Models\TaskSupervisionSession;
use App\Models\TimeEntry;
use App\Models\User;
use App\Scopes\TeamMemberScope;
use App\Services\HR\EmployeeSalaryPeriodService;
use App\Services\HR\WorkCommitmentService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmployeeHrReportService
{
    public function build(User $employee, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $sections = [];
        $section = function (string $key, string $title, Collection $records, array $columns) use (&$sections): void {
            $sections[] = [
                'key' => $key, 'title' => $title, 'columns' => $columns,
                'rows' => $records->map(function (Model $record) use ($columns): array {
                    $row = ['recordId' => $record->getKey() ?? "policy-{$record->year}-{$record->leave_type_id}"];
                    foreach ($columns as $attribute => $label) {
                        $value = data_get($record, $attribute);
                        $row[$attribute] = match (true) {
                            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s T'),
                            $value instanceof BackedEnum => $value->value,
                            is_bool($value) => $value ? 'Yes' : 'No',
                            is_array($value) => implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value)),
                            is_scalar($value), $value === null => $value,
                            default => (string) $value,
                        };
                    }

                    return $row;
                })->values()->all(),
            ];
        };
        $audit = ['created_at' => 'Registered at', 'createdBy.name' => 'Registered by'];
        $workByDay = TimeEntry::where('user_id', $employee->id)->whereNotNull('end_time')
            ->whereDate('start_time', '>=', $from)->whereDate('start_time', '<=', $to)
            ->selectRaw('DATE(start_time) as work_day, SUM(duration_minutes) as worked_minutes')->groupByRaw('DATE(start_time)')
            ->pluck('worked_minutes', 'work_day');
        $approvedOvertimeByDay = EmployeeOvertimeRequest::where('employee_id', $employee->id)->where('status', 'approved')
            ->whereBetween('overtime_date', [$from->toDateString(), $to->toDateString()])->get()
            ->groupBy(fn ($record) => $record->overtime_date->toDateString())->map(fn ($records) => (int) $records->sum('approved_minutes'));
        $attendance = Attendance::where('user_id', $employee->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->orderByDesc('date')->get();
        foreach ($attendance as $record) {
            $date = $record->date->toDateString();
            $expected = $record->expected_clock_out ?: $employee->expected_clock_out;
            $extra = $record->clock_out !== null && is_string($expected) && $expected !== ''
                ? max(0, (int) Carbon::parse($date.' '.$expected)->diffInMinutes($record->clock_out, false)) : 0;
            $record->setAttribute('worked_minutes', (int) $workByDay->get($date, 0));
            $record->setAttribute('extra_minutes', $extra);
            $record->setAttribute('approved_overtime_minutes', $approvedOvertimeByDay->get($date, 0));
            $record->setAttribute('unapproved_overtime_minutes', max(0, $extra - $approvedOvertimeByDay->get($date, 0)));
        }
        $contracts = $employee->employmentContracts()->with('createdBy')
            ->whereDate('start_date', '<=', $to)->orderByDesc('start_date')->get();
        $section('contracts', 'Employment contracts & renewal history', $contracts, [
            'employment_type' => 'Employment type', 'status' => 'Status', 'start_date' => 'Starts', 'end_date' => 'Ends',
            'terminated_on' => 'Terminated on', 'base_salary' => 'Monthly salary (EGP)', 'required_monthly_hours' => 'Required monthly hours',
            'work_days' => 'Work days', 'work_hours_per_day' => 'Hours/day', 'work_start_time' => 'Start time', 'notes' => 'Notes',
            'original_filename' => 'Contract file', ...$audit,
        ]);
        $section('attendance', 'Attendance & lateness', $attendance, [
            'date' => 'Date', 'clock_in' => 'Clock in', 'clock_out' => 'Clock out', 'late_minutes' => 'Late minutes',
            'worked_minutes' => 'Worked minutes', 'idle_minutes' => 'Idle minutes',
            'extra_minutes' => 'Actual overtime minutes', 'approved_overtime_minutes' => 'Payable overtime minutes', 'unapproved_overtime_minutes' => 'Unapproved overtime minutes',
        ]);
        $section('leaves', 'Leave history', EmployeeLeave::with('leaveType', 'reviewer', 'createdBy')->where('employee_id', $employee->id)
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->orderByDesc('start_date')->get(), [
                'leaveType.name' => 'Leave type', 'start_date' => 'From', 'end_date' => 'To', 'working_days' => 'Total working days',
                'is_paid' => 'Paid', 'status' => 'Status', 'reason' => 'Reason', 'reviewer.name' => 'Reviewed by', 'reviewed_at' => 'Reviewed at', 'review_note' => 'Review note', ...$audit,
            ]);
        $section('leave-balances', 'Annual leave balances', $this->leaveBalances($employee, $from->year, $to->year), [
            'year' => 'Year', 'leaveType.name' => 'Leave type', 'allocated_days' => 'Allocated days', 'used_days' => 'Used days', 'remaining_days' => 'Remaining days',
            'balance_source' => 'Allowance source',
        ]);
        $section('permissions', 'Short permissions', EmployeeShortPermission::with('reviewer', 'createdBy')->where('employee_id', $employee->id)->whereBetween('permission_date', [$from->toDateString(), $to->toDateString()])->orderByDesc('permission_date')->get(), [
            'permission_date' => 'Date', 'start_time' => 'From', 'end_time' => 'To', 'duration_minutes' => 'Minutes', 'is_paid' => 'Paid',
            'status' => 'Status', 'reason' => 'Reason', 'reviewer.name' => 'Reviewed by', 'reviewed_at' => 'Reviewed at', 'review_note' => 'Review note', ...$audit,
        ]);
        $section('permission-balances', 'Monthly permission allowances', EmployeePermissionAllowance::where('employee_id', $employee->id)->whereBetween('period', [$from->format('Y-m'), $to->format('Y-m')])->get(), [
            'period' => 'Month', 'allocated_minutes' => 'Allocated minutes', 'used_paid_minutes' => 'Paid minutes used',
        ]);
        $section('overtime', 'Overtime authorizations & actual work', EmployeeOvertimeRequest::with('createdBy', 'reviewer')->where('employee_id', $employee->id)->whereBetween('overtime_date', [$from->toDateString(), $to->toDateString()])->get(), [
            'overtime_date' => 'Date', 'start_time' => 'From', 'end_time' => 'To', 'requested_minutes' => 'Authorized minutes', 'actual_minutes' => 'Actual minutes',
            'approved_minutes' => 'Payable minutes', 'unapproved_minutes' => 'Unapproved minutes', 'payable_amount' => 'Payable (EGP)', 'status' => 'Status',
            'reason' => 'Reason', 'reviewer.name' => 'Reviewed by', 'reviewed_at' => 'Reviewed at', ...$audit,
        ]);
        $advances = EmployeeAdvance::with('settlements', 'approver', 'disburser', 'createdBy')->where('employee_id', $employee->id)
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('created_at', [$from, $to])->orWhereBetween('disbursed_at', [$from, $to])
                    ->orWhereHas('settlements', fn ($settlements) => $settlements->whereBetween('due_on', [$from->toDateString(), $to->toDateString()]));
            })->orderByDesc('created_at')->get();
        $section('advances', 'Salary withdrawals & advances', $advances, [
            'requested_amount' => 'Requested (EGP)', 'disbursed_amount' => 'Withdrawn (EGP)', 'disbursed_at' => 'Exact withdrawal time', 'reason' => 'Reason',
            'status' => 'Status', 'outstanding_amount' => 'Outstanding (EGP)', 'approver.name' => 'Approved by', 'approved_at' => 'Approved at', 'disburser.name' => 'Paid by', ...$audit,
        ]);
        $section('advance-recovery', 'Salary withdrawal deduction records', $advances->flatMap(fn ($advance) => $advance->settlements), [
            'employee_advance_id' => 'Withdrawal ID', 'due_on' => 'Salary deduction date', 'scheduled_amount' => 'Amount (EGP)',
            'status' => 'Status', 'settled_at' => 'Applied at', 'salary_adjustment_id' => 'Salary transaction ID',
        ]);
        $section('deductions', 'Direct salary deductions', EmployeeDeduction::with('approver', 'createdBy')->where('user_id', $employee->id)
            ->where('source', EmployeeDeduction::SOURCE_DIRECT_MANUAL)->whereBetween('deduction_date', [$from->toDateString(), $to->toDateString()])->get(), [
                'deduction_date' => 'Date', 'occurred_at' => 'Exact deduction time', 'amount' => 'Amount (EGP)', 'reason' => 'Reason', 'status' => 'Status',
                'approver.name' => 'Approved by', 'approved_at' => 'Approved at', 'rejection_reason' => 'Rejection reason', ...$audit,
            ]);
        $section('late-deductions', 'Late salary deductions', EmployeeLateDeduction::where('employee_id', $employee->id)->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])->get(), [
            'attendance_date' => 'Date', 'original_late_minutes' => 'Late minutes', 'eligible_late_minutes' => 'Deductible minutes', 'occurrence_number' => 'Occurrence', 'calculated_amount' => 'Calculated (EGP)',
            'override_amount' => 'Override (EGP)', 'status' => 'Status', 'override_reason' => 'Override reason', 'salary_adjustment_id' => 'Salary transaction ID',
        ]);
        $section('salary-transactions', 'All payroll transactions', SalaryAdjustment::with('approver', 'createdBy')->where('user_id', $employee->id)
            ->whereRaw('(payroll_year * 100 + payroll_month) BETWEEN ? AND ?', [$from->year * 100 + $from->month, $to->year * 100 + $to->month])->orderBy('adjustment_date')->get(), [
                'payroll_year' => 'Year', 'payroll_month' => 'Month', 'adjustment_type' => 'Type', 'signed_amount' => 'Signed amount (EGP)', 'reason' => 'Reason',
                'status' => 'Status', 'adjustment_date' => 'Date', 'approver.name' => 'Approved by', 'approved_at' => 'Approved at', ...$audit,
            ]);
        $section('partial-pay', 'Partial salary calculation history', EmployeePartialPay::where('employee_id', $employee->id)
            ->whereDate('service_start_date', '<=', $to)->whereDate('service_end_date', '>=', $from)->get(), [
                'kind' => 'Kind', 'service_start_date' => 'From', 'service_end_date' => 'To', 'payable_days' => 'Payable days',
                'gross_amount' => 'Gross salary (EGP)', 'status' => 'Status', 'approved_at' => 'Approved at',
            ]);
        // Include all assigned tasks existing by the period end, including
        // secondary assignments and tasks opened before the reporting period.
        $tasks = Task::with(['project' => fn ($query) => $query->withoutGlobalScope(TeamMemberScope::class)])
            ->whereHas('assignees', fn ($query) => $query->where('user_id', $employee->id))
            ->where('created_at', '<=', $to)->get();
        $section('tasks', 'Assigned tasks & work performance', $tasks, [
            'title' => 'Task', 'project.title' => 'Project', 'status' => 'Status', 'priority' => 'Priority',
            'due_date' => 'Due date', 'estimated_hours' => 'Estimated hours', 'reopen_count' => 'Reopens', 'created_at' => 'Created at', 'updated_at' => 'Last activity',
        ]);
        // The controller has authorized this employee's report. Its limited
        // project metadata follows the employee, not the reviewer's memberships.
        $section('projects', 'Employee projects', Project::withoutGlobalScope(TeamMemberScope::class)->whereHas('members', fn ($query) => $query->where('user_id', $employee->id))->where('created_at', '<=', $to)->get(), ['title' => 'Project', 'status' => 'Status', 'created_at' => 'Created at']);
        $section('time-entries', 'Tracked work sessions', TimeEntry::with(['trackable' => fn (MorphTo $trackable) => $trackable->constrain([
            Project::class => fn ($query) => $query->withoutGlobalScope(TeamMemberScope::class),
        ])])->where('user_id', $employee->id)
            ->whereDate('start_time', '>=', $from)->whereDate('start_time', '<=', $to)->orderByDesc('start_time')->get(), [
                'trackable.title' => 'Work item (task/project)', 'start_time' => 'Started at', 'end_time' => 'Ended at', 'duration_minutes' => 'Worked minutes',
            ]);
        $section('evaluations', 'Performance evaluations', PerformanceEvaluation::with('period', 'evaluator')->where('user_id', $employee->id)
            ->whereHas('period', fn ($query) => $query->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from))->get(), [
                'period.name' => 'Period', 'productivity_total' => 'Productivity / 25', 'technical_quality_total' => 'Technical quality / 30',
                'soft_skills_total' => 'Soft skills / 25', 'innovation_score' => 'Innovation / 20', 'total_score' => 'Total / 100',
                'status' => 'Status', 'evaluator.name' => 'Evaluated by', 'evaluated_at' => 'Evaluated at', 'evaluator_notes' => 'Notes',
                'work_commitment_snapshot.requiredMinutes' => 'Required work minutes at evaluation', 'work_commitment_snapshot.workedMinutes' => 'Tracked minutes at evaluation',
            ]);
        $section('supervision', 'Supervision sessions (separate from work time)', TaskSupervisionSession::with('task')->where('supervisor_id', $employee->id)
            ->whereBetween('started_at', [$from, $to])->get(), [
                'task.title' => 'Task', 'started_at' => 'Started at', 'ended_at' => 'Ended at', 'duration_minutes' => 'Minutes', 'notes' => 'Notes', 'stop_reason' => 'Stop reason',
            ]);
        $section('help-requests', 'Task help requests', TaskHelpRequest::with('task')->where('requested_by', $employee->id)->whereBetween('created_at', [$from, $to])->get(), [
            'task.title' => 'Task', 'message' => 'Message', 'status' => 'Status', 'created_at' => 'Requested at', 'resolved_at' => 'Resolved at', 'resolution_note' => 'Resolution',
        ]);
        $section('documents', 'Employee documents', EmployeeDocument::with('uploadedBy')->where('employee_id', $employee->id)->where('created_at', '<=', $to)->get(), [
            'name' => 'Document', 'document_type' => 'Type', 'original_filename' => 'File name', 'mime_type' => 'Format', 'size' => 'Size (bytes)', 'uploadedBy.name' => 'Uploaded by', 'created_at' => 'Uploaded at',
        ]);
        $salaryPeriods = [];
        for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonth()) {
            $salaryPeriods[] = app(EmployeeSalaryPeriodService::class)->calculate($employee, $month);
        }

        return [
            'employee' => ['id' => $employee->id, 'name' => $employee->name, 'email' => $employee->email, 'specialization' => $employee->specialization, 'skills' => $employee->skills ?? [], 'roles' => $employee->getRoleNames()->all(), 'joinedAt' => $employee->created_at?->toISOString(), 'isActive' => (bool) $employee->is_active],
            'period' => ['dateFrom' => $from->toDateString(), 'dateTo' => $to->toDateString()],
            'generatedAt' => now()->toISOString(), 'timezone' => config('app.timezone'),
            'workCommitment' => app(WorkCommitmentService::class)->summary($employee, $from, $to),
            'salaryPeriods' => $salaryPeriods,
            'sections' => $sections,
        ];
    }

    private function leaveBalances(User $employee, int $fromYear, int $toYear): Collection
    {
        $recorded = EmployeeLeaveBalance::where('employee_id', $employee->id)->whereBetween('year', [$fromYear, $toYear])->get();
        $types = LeaveType::where('is_paid', true)->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $recorded->pluck('leave_type_id')))->get();
        $byYearAndType = $recorded->keyBy(fn ($balance) => $balance->year.'-'.$balance->leave_type_id);
        $balances = collect();
        for ($year = $fromYear; $year <= $toYear; $year++) {
            foreach ($types as $type) {
                $balance = $byYearAndType->get($year.'-'.$type->id);
                $source = $balance === null ? 'Current policy allowance (no recorded usage)' : 'Recorded balance';
                $balance ??= new EmployeeLeaveBalance(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year, 'allocated_days' => $type->annual_allowance_days, 'used_days' => 0]);
                $balance->setRelation('leaveType', $type);
                $balance->setAttribute('balance_source', $source);
                $balance->setAttribute('remaining_days', max(0, (float) $balance->allocated_days - (float) $balance->used_days));
                $balances->push($balance);
            }
        }

        return $balances;
    }
}
