<?php

namespace App\Services\HR;

use App\Models\Attendance;
use App\Models\EmployeeOvertimeRequest;
use App\Models\HrPolicy;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeRequestService
{
    private const DEFAULT_DAILY_RATE_DIVISOR = 30;

    private const DEFAULT_OVERTIME_MULTIPLIER = 1.5;

    private const DEFAULT_WORK_HOURS_PER_DAY = 8;

    public function durationMinutes(string $startTime, string $endTime): int
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $startTime));
        [$endHour, $endMinute] = array_map('intval', explode(':', $endTime));
        $durationMinutes = (($endHour * 60) + $endMinute) - (($startHour * 60) + $startMinute);

        if (
            $startHour > 23
            || $startMinute > 59
            || $endHour > 23
            || $endMinute > 59
            || $durationMinutes <= 0
            || $durationMinutes > 24 * 60
        ) {
            throw ValidationException::withMessages([
                'endTime' => ['The end time must be after the start time and within the same day.'],
            ]);
        }

        return $durationMinutes;
    }

    public function assertSubmissionAllowed(
        User $employee,
        string $overtimeDate,
        string $startTime,
        string $endTime,
    ): void {
        $attendance = Attendance::query()
            ->where('user_id', $employee->id)
            ->whereDate('date', $overtimeDate)
            ->first();

        $this->assertAuthorizationWindow($employee, $attendance, $startTime, $endTime);
        $this->assertNoOverlappingRequest($employee->id, $overtimeDate, $startTime, $endTime, null);

        if ($this->requiresPreapproval($overtimeDate) && $overtimeDate <= today()->toDateString()) {
            throw ValidationException::withMessages([
                'overtimeDate' => ['Overtime requests must be submitted before the overtime date.'],
            ]);
        }
    }

    public function approve(
        EmployeeOvertimeRequest $overtimeRequest,
        User $reviewer,
        ?string $reviewNote = null,
    ): EmployeeOvertimeRequest {
        return DB::transaction(function () use ($overtimeRequest, $reviewer, $reviewNote): EmployeeOvertimeRequest {
            $overtimeRequest = EmployeeOvertimeRequest::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($overtimeRequest->id);
            $this->assertPending($overtimeRequest);

            /** @var User $employee */
            $employee = $overtimeRequest->employee;
            $attendance = Attendance::query()
                ->where('user_id', $employee->id)
                ->whereDate('date', $overtimeRequest->overtime_date)
                ->first();
            $this->assertAuthorizationWindow(
                $employee,
                $attendance,
                $overtimeRequest->start_time,
                $overtimeRequest->end_time,
            );
            $this->assertNoOverlappingRequest(
                $employee->id,
                $overtimeRequest->overtime_date->toDateString(),
                $overtimeRequest->start_time,
                $overtimeRequest->end_time,
                $overtimeRequest->id,
            );

            $policy = $this->policyForDate($overtimeRequest->overtime_date);
            $hourlyRate = $this->hourlyRate($employee, $policy);

            $overtimeRequest->update([
                'status' => EmployeeOvertimeRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
                'policy_version_id' => $policy?->id,
                'hourly_rate' => $hourlyRate,
                'overtime_multiplier' => $this->overtimeMultiplier($policy),
            ]);

            return $this->synchronize($overtimeRequest);
        });
    }

    public function reject(
        EmployeeOvertimeRequest $overtimeRequest,
        User $reviewer,
        ?string $reviewNote = null,
    ): EmployeeOvertimeRequest {
        return DB::transaction(function () use ($overtimeRequest, $reviewer, $reviewNote): EmployeeOvertimeRequest {
            $overtimeRequest = EmployeeOvertimeRequest::query()
                ->lockForUpdate()
                ->findOrFail($overtimeRequest->id);
            $this->assertPending($overtimeRequest);

            $overtimeRequest->update([
                'status' => EmployeeOvertimeRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
            ]);

            return $overtimeRequest->fresh();
        });
    }

    public function cancel(
        EmployeeOvertimeRequest $overtimeRequest,
        User $actor,
        ?string $reviewNote = null,
    ): EmployeeOvertimeRequest {
        return DB::transaction(function () use ($overtimeRequest, $actor, $reviewNote): EmployeeOvertimeRequest {
            $overtimeRequest = EmployeeOvertimeRequest::query()
                ->lockForUpdate()
                ->findOrFail($overtimeRequest->id);
            $this->assertPending($overtimeRequest);

            $overtimeRequest->update([
                'status' => EmployeeOvertimeRequest::STATUS_CANCELLED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
            ]);

            return $overtimeRequest->fresh();
        });
    }

    public function synchronize(EmployeeOvertimeRequest $overtimeRequest): EmployeeOvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequest): EmployeeOvertimeRequest {
            $overtimeRequest = EmployeeOvertimeRequest::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($overtimeRequest->id);

            if ($overtimeRequest->status !== EmployeeOvertimeRequest::STATUS_APPROVED) {
                return $overtimeRequest;
            }

            $attendance = Attendance::query()
                ->where('user_id', $overtimeRequest->employee_id)
                ->whereDate('date', $overtimeRequest->overtime_date)
                ->lockForUpdate()
                ->first();

            if ($attendance === null || $attendance->clock_out === null) {
                return $overtimeRequest;
            }

            [$actualMinutes, $approvedMinutes] = $this->actualAndApprovedMinutes($overtimeRequest, $attendance);
            $payableAmount = $approvedMinutes > 0
                ? round(
                    ((float) ($overtimeRequest->hourly_rate ?? 0))
                    * ((float) ($overtimeRequest->overtime_multiplier ?? self::DEFAULT_OVERTIME_MULTIPLIER))
                    * ($approvedMinutes / 60),
                    2
                )
                : 0.0;

            $overtimeRequest->update([
                'actual_minutes' => $actualMinutes,
                'approved_minutes' => $approvedMinutes,
                'payable_amount' => $payableAmount,
            ]);

            $adjustment = SalaryAdjustment::query()
                ->where('reference_type', EmployeeOvertimeRequest::class)
                ->where('reference_id', $overtimeRequest->id)
                ->lockForUpdate()
                ->first();

            if ($approvedMinutes > 0) {
                $attributes = [
                    'user_id' => $overtimeRequest->employee_id,
                    'adjustment_type' => SalaryAdjustment::TYPE_OVERTIME,
                    'amount' => $payableAmount,
                    'reason' => $overtimeRequest->reason,
                    'adjustment_date' => $overtimeRequest->overtime_date,
                    'payroll_month' => $overtimeRequest->overtime_date->month,
                    'payroll_year' => $overtimeRequest->overtime_date->year,
                    'status' => SalaryAdjustment::STATUS_APPROVED,
                    'approved_by' => $overtimeRequest->reviewed_by,
                    'approved_at' => $overtimeRequest->reviewed_at,
                    'notes' => $overtimeRequest->review_note,
                ];

                if ($adjustment === null) {
                    SalaryAdjustment::create(array_merge($attributes, [
                        'reference_type' => EmployeeOvertimeRequest::class,
                        'reference_id' => $overtimeRequest->id,
                    ]));
                } elseif ($adjustment->status !== SalaryAdjustment::STATUS_PROCESSED) {
                    $adjustment->update($attributes);
                }
            } elseif ($adjustment !== null && $adjustment->status !== SalaryAdjustment::STATUS_PROCESSED) {
                $adjustment->cancel('No approved overtime was worked.');
            }

            return $overtimeRequest->fresh();
        });
    }

    public function synchronizeApprovedRequestsForAttendance(Attendance $attendance): void
    {
        if ($attendance->clock_out === null) {
            return;
        }

        EmployeeOvertimeRequest::query()
            ->where('employee_id', $attendance->user_id)
            ->whereDate('overtime_date', $attendance->date)
            ->where('status', EmployeeOvertimeRequest::STATUS_APPROVED)
            ->get()
            ->each(fn (EmployeeOvertimeRequest $request): EmployeeOvertimeRequest => $this->synchronize($request));
    }

    private function actualAndApprovedMinutes(
        EmployeeOvertimeRequest $overtimeRequest,
        Attendance $attendance,
    ): array {
        /** @var User $employee */
        $employee = $overtimeRequest->employee;
        $expectedClockOut = $this->expectedClockOut($employee, $attendance);

        if ($expectedClockOut === null) {
            return [0, 0];
        }

        $date = $overtimeRequest->overtime_date->toDateString();
        $expectedAt = Carbon::parse($date.' '.$expectedClockOut);
        $clockOut = $attendance->clock_out;

        if ($clockOut->lte($expectedAt)) {
            return [0, 0];
        }

        $actualMinutes = (int) $expectedAt->diffInMinutes($clockOut);
        $requestStart = Carbon::parse($date.' '.$overtimeRequest->start_time);
        $requestEnd = Carbon::parse($date.' '.$overtimeRequest->end_time);
        $overlapStart = $expectedAt->greaterThan($requestStart) ? $expectedAt : $requestStart;
        $overlapEnd = $clockOut->lessThan($requestEnd) ? $clockOut : $requestEnd;
        $approvedMinutes = $overlapEnd->greaterThan($overlapStart)
            ? (int) $overlapStart->diffInMinutes($overlapEnd)
            : 0;

        return [$actualMinutes, $approvedMinutes];
    }

    private function assertAuthorizationWindow(
        User $employee,
        ?Attendance $attendance,
        string $startTime,
        string $endTime,
    ): void {
        $this->durationMinutes($startTime, $endTime);
        $expectedClockOut = $this->expectedClockOut($employee, $attendance);

        if ($expectedClockOut === null) {
            throw ValidationException::withMessages([
                'startTime' => ['The employee does not have an expected clock-out time.'],
            ]);
        }

        if ($startTime < substr($expectedClockOut, 0, 5)) {
            throw ValidationException::withMessages([
                'startTime' => ['The overtime authorization window must start at or after the expected clock-out time.'],
            ]);
        }
    }

    /**
     * Prevent the same minutes from being paid twice via overlapping
     * authorizations for the same employee and date.
     */
    private function assertNoOverlappingRequest(
        int $employeeId,
        string $overtimeDate,
        string $startTime,
        string $endTime,
        ?int $exceptId,
    ): void {
        $query = EmployeeOvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->whereDate('overtime_date', $overtimeDate)
            ->whereIn('status', [
                EmployeeOvertimeRequest::STATUS_PENDING,
                EmployeeOvertimeRequest::STATUS_APPROVED,
            ])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'startTime' => ['An overlapping overtime authorization already exists for this date.'],
            ]);
        }
    }

    private function expectedClockOut(User $employee, ?Attendance $attendance = null): ?string
    {
        $expectedClockOut = $attendance?->expected_clock_out ?: $employee->expected_clock_out;

        return is_string($expectedClockOut) && $expectedClockOut !== '' ? $expectedClockOut : null;
    }

    private function policyForDate(Carbon|string $date): ?HrPolicy
    {
        return HrPolicy::query()
            ->effectiveOn($date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    private function requiresPreapproval(string $overtimeDate): bool
    {
        $policy = $this->policyForDate($overtimeDate);

        return (bool) ($policy?->settings['overtimeRequiresPreapproval'] ?? true);
    }

    private function hourlyRate(User $employee, ?HrPolicy $policy): ?float
    {
        // Client decision 1: manual hourly rate wins when set by Admin.
        if ($employee->hourly_rate !== null && (float) $employee->hourly_rate > 0) {
            return round((float) $employee->hourly_rate, 4);
        }

        if ($employee->salary === null) {
            return null;
        }

        $divisor = (int) ($policy?->settings['dailyRateDivisor'] ?? self::DEFAULT_DAILY_RATE_DIVISOR);
        $workHoursPerDay = (float) ($employee->work_hours_per_day ?? self::DEFAULT_WORK_HOURS_PER_DAY);

        if ($divisor <= 0) {
            $divisor = self::DEFAULT_DAILY_RATE_DIVISOR;
        }

        if ($workHoursPerDay <= 0) {
            $workHoursPerDay = self::DEFAULT_WORK_HOURS_PER_DAY;
        }

        return round((float) $employee->salary / ($divisor * $workHoursPerDay), 4);
    }

    private function overtimeMultiplier(?HrPolicy $policy): float
    {
        $multiplier = $policy?->settings['overtimeMultiplier'] ?? self::DEFAULT_OVERTIME_MULTIPLIER;

        return is_numeric($multiplier) && (float) $multiplier >= 0
            ? (float) $multiplier
            : self::DEFAULT_OVERTIME_MULTIPLIER;
    }

    private function assertPending(EmployeeOvertimeRequest $overtimeRequest): void
    {
        if ($overtimeRequest->status !== EmployeeOvertimeRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending overtime requests can be reviewed or cancelled.'],
            ]);
        }
    }
}
