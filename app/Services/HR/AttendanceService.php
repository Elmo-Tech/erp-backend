<?php

namespace App\Services\HR;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AttendanceService
{
    /**
     * Clock in a user for today.
     * Calculates late minutes and handles grace days.
     */
    public function clockIn(User $user, ?Carbon $clockInTime = null): Attendance
    {
        $now = $clockInTime ?? Carbon::now();
        $today = $now->toDateString();

        // Check if already clocked in today
        $existing = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Get expected times from user settings
        $expectedClockIn = $user->work_start_time ?? '10:00:00';
        $expectedClockOut = $user->expected_clock_out;

        // Create attendance record
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $today,
            'clock_in' => $now,
            'expected_clock_in' => $expectedClockIn,
            'expected_clock_out' => $expectedClockOut,
            'late_minutes' => 0,
            'idle_minutes' => 0,
            'deduction_minutes' => 0,
            'used_grace_day' => false,
        ]);

        // Calculate late minutes
        $lateMinutes = $attendance->calculateLateMinutes();
        $attendance->late_minutes = $lateMinutes;

        // Calculate deduction
        $deduction = $attendance->calculateDeduction($user);
        $attendance->deduction_minutes = $deduction['deduction_minutes'];
        $attendance->used_grace_day = $deduction['used_grace_day'];

        // Use grace day if needed
        if ($deduction['used_grace_day']) {
            $user->useGraceDay();
        }

        // Add deduction to user's total if applicable
        if ($deduction['deduction_minutes'] > 0 && $user->salary) {
            $hourlyRate = $user->salary / (30 * $user->work_hours_per_day); // Monthly salary / (days * hours)
            $deductionAmount = ($deduction['deduction_minutes'] / 60) * $hourlyRate;
            $user->addDeduction($deductionAmount);
        }

        $attendance->save();

        return $attendance;
    }

    /**
     * Clock out a user for today.
     * Calculates idle minutes.
     */
    public function clockOut(User $user, ?Carbon $clockOutTime = null): ?Attendance
    {
        $now = $clockOutTime ?? Carbon::now();
        $today = $now->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->whereNull('clock_out')
            ->first();

        if (!$attendance) {
            return null;
        }

        $attendance->clock_out = $now;
        $attendance->idle_minutes = $attendance->calculateIdleMinutes();
        $attendance->save();

        return $attendance;
    }

    /**
     * Get today's attendance for a user.
     */
    public function getTodayAttendance(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->where('date', Carbon::today()->toDateString())
            ->first();
    }

    /**
     * Get attendance records for a user within a date range.
     */
    public function getUserAttendance(User $user, ?string $startDate = null, ?string $endDate = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Attendance::where('user_id', $user->id);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return $query->orderBy('date', 'desc')->paginate($perPage);
    }

    /**
     * Get all attendance records with filters.
     */
    public function getAllAttendance(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Attendance::with('user');

        if (!empty($filters['userId'])) {
            $query->where('user_id', $filters['userId']);
        }

        if (!empty($filters['startDate'])) {
            $query->where('date', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->where('date', '<=', $filters['endDate']);
        }

        if (!empty($filters['date'])) {
            $query->where('date', $filters['date']);
        }

        // Only show employees
        $query->whereHas('user', fn($q) => $q->where('is_employee', true));

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
        $totalWorkedMinutes = $attendances->sum(fn($a) => $a->getWorkedMinutes());

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
            if (!$start->isFriday() && !$start->isSaturday()) {
                $workDays++;
            }
            $start->addDay();
        }

        return $workDays;
    }

    /**
     * Update attendance record manually (admin only).
     */
    public function updateAttendance(Attendance $attendance, array $data, User $admin): Attendance
    {
        $updateData = [];

        if (isset($data['clockIn'])) {
            $updateData['clock_in'] = $data['clockIn'];
        }

        if (isset($data['clockOut'])) {
            $updateData['clock_out'] = $data['clockOut'];
        }

        if (isset($data['notes'])) {
            $updateData['notes'] = $data['notes'];
        }

        if (isset($data['lateMinutes'])) {
            $updateData['late_minutes'] = $data['lateMinutes'];
        }

        if (isset($data['deductionMinutes'])) {
            $updateData['deduction_minutes'] = $data['deductionMinutes'];
        }

        $attendance->update($updateData);

        // Recalculate idle minutes if clock times changed
        if (isset($data['clockIn']) || isset($data['clockOut'])) {
            $attendance->idle_minutes = $attendance->calculateIdleMinutes();
            $attendance->save();
        }

        return $attendance->fresh();
    }

    /**
     * Reset grace days for all employees (run monthly via scheduler).
     */
    public function resetAllGraceDays(): int
    {
        return User::employees()
            ->update([
                'grace_days_used' => 0,
                'grace_days_reset_date' => Carbon::now()->startOfMonth(),
            ]);
    }
}
