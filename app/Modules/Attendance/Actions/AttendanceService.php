<?php

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function __construct(
        private ClockInAction $clockInAction,
        private ClockOutAction $clockOutAction
    ) {}

    /**
     * Clock in a user for today.
     * Calculates late minutes and handles grace days.
     */
    public function clockIn(User $user, ?Carbon $clockInTime = null): Attendance
    {
        return $this->clockInAction->execute($user, $clockInTime);
    }

    /**
     * Clock out a user for today.
     * Calculates idle minutes.
     */
    public function clockOut(User $user, ?Carbon $clockOutTime = null): ?Attendance
    {
        return $this->clockOutAction->execute($user, $clockOutTime);
    }

    /**
     * Get today's attendance for a user.
     */
    public function getTodayAttendance(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->whereDate('date', Carbon::today('Africa/Cairo')->toDateString())
            ->first();
    }

    /**
     * Get attendance records for a user within a date range.
     */
    public function getUserAttendance(User $user, ?string $startDate = null, ?string $endDate = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Attendance::where('user_id', $user->id);

        if ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        }

        return $query->orderBy('date', 'desc')->paginate($perPage);
    }

    /**
     * Get all attendance records with filters.
     */
    public function getAllAttendance(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Attendance::with('user');

        if (! empty($filters['userId'])) {
            $userId = is_numeric($filters['userId']) ? (int) $filters['userId'] : $filters['userId'];
            $query->where('user_id', $userId);
        }

        if (! empty($filters['startDate'])) {
            $query->whereDate('date', '>=', $filters['startDate']);
        }

        if (! empty($filters['endDate'])) {
            $query->whereDate('date', '<=', $filters['endDate']);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        // Only show employees
        $query->whereHas('user', fn ($q) => $q->where('is_employee', true));

        return $query->orderBy('date', 'desc')->paginate($perPage);
    }

    /**
     * Get monthly summary for a user.
     */
    public function getMonthlySummary(User $user, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalDays = $attendances->count();
        $totalLateMinutes = $attendances->sum('late_minutes');
        $totalIdleMinutes = $attendances->sum('idle_minutes');
        $totalDeductionMinutes = $attendances->sum('deduction_minutes');
        $graceDaysUsed = $attendances->where('used_grace_day', true)->count();
        $totalWorkedMinutes = $attendances->sum(fn ($a) => $a->getWorkedMinutes());

        // Calculate deduction amount
        $deductionAmount = 0;
        if ($user->salary && $totalDeductionMinutes > 0) {
            $hourlyRate = $user->salary / (30 * $user->work_hours_per_day);
            $deductionAmount = ($totalDeductionMinutes / 60) * $hourlyRate;
        }

        return [
            'year' => $year,
            'month' => $month,
            'totalDays' => $totalDays,
            'totalLateMinutes' => $totalLateMinutes,
            'totalIdleMinutes' => $totalIdleMinutes,
            'totalDeductionMinutes' => $totalDeductionMinutes,
            'graceDaysUsed' => $graceDaysUsed,
            'graceDaysRemaining' => max(0, $user->grace_days_allowed - $user->grace_days_used),
            'totalWorkedMinutes' => $totalWorkedMinutes,
            'totalWorkedHours' => round($totalWorkedMinutes / 60, 2),
            'deductionAmount' => round($deductionAmount, 2),
            'expectedWorkDays' => $this->getWorkDaysInMonth($year, $month),
        ];
    }

    /**
     * Get work days in a month (excluding weekends).
     */
    private function getWorkDaysInMonth(int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();
        $workDays = 0;

        while ($start->lte($end)) {
            // Exclude Friday and Saturday (Middle East weekend)
            if (! $start->isFriday() && ! $start->isSaturday()) {
                $workDays++;
            }
            $start->addDay();
        }

        return $workDays;
    }

    /**
     * Create attendance record manually (admin only).
     * Auto-calculates late minutes, grace day usage, and deductions.
     */
    public function createAttendance(array $data, User $admin): Attendance
    {
        $user = User::findOrFail($data['userId']);

        // Parse clock times - keep the time as-is without timezone conversion
        // The frontend sends local time, we store it as-is
        $clockIn = Carbon::parse($data['clockIn']);
        $clockOut = isset($data['clockOut']) ? Carbon::parse($data['clockOut']) : null;

        // Get expected clock in time
        $expectedClockIn = $user->work_start_time ?? '10:00:00';

        // Calculate late minutes automatically
        $expectedTime = Carbon::parse($data['date'].' '.$expectedClockIn);
        $lateMinutes = 0;

        if ($clockIn->gt($expectedTime)) {
            $lateMinutes = $expectedTime->diffInMinutes($clockIn);
        }

        // Auto-calculate grace day and deduction
        $usedGraceDay = false;
        $deductionMinutes = 0;

        $lateThreshold = (int) config('attendance.late_threshold_minutes', 15);

        if ($lateMinutes > $lateThreshold) {
            // Check if user has grace days remaining
            if ($user->hasGraceDaysRemaining()) {
                $usedGraceDay = true;
                $deductionMinutes = 0;
                $user->useGraceDay();
            } else {
                // No grace days - calculate deduction in threshold-minute increments
                $deductionMinutes = floor($lateMinutes / $lateThreshold) * $lateThreshold;
            }
        }

        $attendance = Attendance::create([
            'user_id' => $data['userId'],
            'date' => $data['date'],
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'expected_clock_in' => $expectedClockIn,
            'expected_clock_out' => $user->expected_clock_out,
            'late_minutes' => $lateMinutes,
            'is_late' => $lateMinutes > 0,
            'idle_minutes' => 0,
            'deduction_minutes' => (int) $deductionMinutes,
            'used_grace_day' => $usedGraceDay,
            'notes' => $data['notes'] ?? null,
        ]);

        // Calculate idle minutes if clock_out is set
        if ($attendance->clock_out) {
            $attendance->idle_minutes = $attendance->calculateIdleMinutes();
            $attendance->save();
        }

        return $attendance->load('user');
    }

    /**
     * Update attendance record manually (admin only).
     * Auto-recalculates late minutes, grace day usage, and deductions when clock times change.
     */
    public function updateAttendance(Attendance $attendance, array $data, User $admin): Attendance
    {
        $updateData = [];
        $user = $attendance->user;
        $recalculateLate = false;

        if (isset($data['clockIn'])) {
            $updateData['clock_in'] = Carbon::parse($data['clockIn']);
            $recalculateLate = true;
        }

        if (isset($data['clockOut'])) {
            $updateData['clock_out'] = Carbon::parse($data['clockOut']);
        }

        if (isset($data['notes'])) {
            $updateData['notes'] = $data['notes'];
        }

        // Recalculate late minutes and deductions if clock_in changed
        if ($recalculateLate) {
            $clockIn = $updateData['clock_in'];
            $expectedClockIn = $attendance->expected_clock_in ?? $user->work_start_time ?? '10:00:00';
            $expectedTime = Carbon::parse($attendance->date->format('Y-m-d').' '.$expectedClockIn);

            $lateMinutes = 0;
            if ($clockIn->gt($expectedTime)) {
                $lateMinutes = $expectedTime->diffInMinutes($clockIn);
            }

            $updateData['late_minutes'] = $lateMinutes;
            $updateData['is_late'] = $lateMinutes > 0;

            // Handle grace day changes
            $oldUsedGraceDay = (bool) $attendance->used_grace_day;
            $newUsedGraceDay = false;
            $deductionMinutes = 0;

            $lateThreshold = (int) config('attendance.late_threshold_minutes', 15);

            if ($lateMinutes > $lateThreshold) {
                // Need grace day or deduction
                if ($oldUsedGraceDay) {
                    // Already using grace day, keep it
                    $newUsedGraceDay = true;
                    $deductionMinutes = 0;
                } elseif ($user->hasGraceDaysRemaining()) {
                    // Use a new grace day
                    $newUsedGraceDay = true;
                    $deductionMinutes = 0;
                    $user->useGraceDay();
                } else {
                    // No grace days available
                    $deductionMinutes = floor($lateMinutes / $lateThreshold) * $lateThreshold;
                }
            } else {
                // Not late enough for grace day/deduction
                if ($oldUsedGraceDay) {
                    // Restore the grace day
                    $user->restoreGraceDay();
                }
            }

            $updateData['used_grace_day'] = $newUsedGraceDay;
            $updateData['deduction_minutes'] = (int) $deductionMinutes;
        }

        $attendance->update($updateData);

        // Recalculate idle minutes if clock times changed
        if (isset($data['clockIn']) || isset($data['clockOut'])) {
            $attendance->idle_minutes = $attendance->calculateIdleMinutes();
            $attendance->save();
        }

        return $attendance->fresh()->load('user');
    }

    /**
     * Reset grace days for all employees (run monthly via scheduler).
     */
    public function resetAllGraceDays(): int
    {
        return User::employees()
            ->update([
                'grace_days_used' => 0,
                'grace_days_reset_date' => Carbon::now('Africa/Cairo')->startOfMonth(),
                'total_deductions' => 0, // Reset monthly salary deductions
            ]);
    }
}
