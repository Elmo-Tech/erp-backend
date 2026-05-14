<?php

namespace App\Modules\HR\Services;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\Settings\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function checkInForToday(User $user, ?Carbon $clockInAt = null): Attendance
    {
        $clockInAt ??= Carbon::now();
        $date = $clockInAt->toDateString();

        $existing = $this->findForDate($user, $date);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return Attendance::create($this->attendancePayload($user, $clockInAt, $date));
        } catch (QueryException $exception) {
            $existing = $this->findForDate($user, $date);

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function findForDate(User $user, string $date): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function attendancePayload(User $user, Carbon $clockInAt, string $date): array
    {
        $status = 'present';

        /** @var EmployeeProfile|null $profile */
        $profile = EmployeeProfile::with('shift')
            ->where('user_id', $user->id)
            ->first();

        $shift = $profile?->shift;
        $expectedClockIn = null;
        $expectedClockOut = null;

        if ($shift !== null) {
            $expectedClockIn = $shift->start_time;
            $expectedClockOut = $shift->end_time;

            if ($clockInAt->gt($shift->latenessDeadlineToday())) {
                $status = 'late';
            }
        }

        return [
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockInAt,
            'clock_out' => null,
            'status' => $status,
            'total_worked_minutes' => 0,
            'late_minutes' => 0,
            'is_late' => $status === 'late',
            'idle_minutes' => 0,
            'deduction_minutes' => 0,
            'used_grace_day' => false,
            'expected_clock_in' => $expectedClockIn,
            'expected_clock_out' => $expectedClockOut,
        ];
    }
}
