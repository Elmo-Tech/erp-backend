<?php

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\User\Models\User;
use Carbon\Carbon;

class ClockInAction
{
    public function execute(User $user, ?Carbon $clockInTime = null): Attendance
    {
        $now = $clockInTime ?? Carbon::now('Africa/Cairo');
        $today = $now->toDateString();

        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing) {
            return $existing;
        }

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $today,
            'clock_in' => $now,
            'expected_clock_in' => $user->work_start_time ?? '10:00:00',
            'expected_clock_out' => $user->expected_clock_out,
            'late_minutes' => 0,
            'idle_minutes' => 0,
            'deduction_minutes' => 0,
            'used_grace_day' => false,
        ]);

        $lateMinutes = $attendance->calculateLateMinutes();
        $deduction = $attendance->fill(['late_minutes' => $lateMinutes])->calculateDeduction($user);

        $attendance->late_minutes = $lateMinutes;
        $attendance->is_late = $lateMinutes > 0;
        $attendance->deduction_minutes = $deduction['deduction_minutes'];
        $attendance->used_grace_day = $deduction['used_grace_day'];

        if ($deduction['used_grace_day']) {
            $user->useGraceDay();
        }

        if ($deduction['deduction_minutes'] > 0 && $user->salary) {
            $hourlyRate = $user->salary / (30 * $user->work_hours_per_day);
            $user->addDeduction(($deduction['deduction_minutes'] / 60) * $hourlyRate);
        }

        $attendance->save();

        return $attendance;
    }
}
