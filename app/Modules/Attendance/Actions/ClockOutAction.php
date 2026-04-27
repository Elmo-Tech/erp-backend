<?php

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\User\Models\User;
use Carbon\Carbon;

class ClockOutAction
{
    public function execute(User $user, ?Carbon $clockOutTime = null): ?Attendance
    {
        $now = $clockOutTime ?? Carbon::now('Africa/Cairo');
        $today = $now->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->whereNull('clock_out')
            ->first();

        if (! $attendance) {
            return null;
        }

        $attendance->clock_out = $now;
        $attendance->idle_minutes = $attendance->calculateIdleMinutes();
        $attendance->save();

        return $attendance;
    }
}
