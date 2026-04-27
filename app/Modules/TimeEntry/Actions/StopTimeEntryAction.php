<?php

namespace App\Modules\TimeEntry\Actions;

use App\Events\TimerUpdated;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use RuntimeException;

class StopTimeEntryAction
{
    public function execute(User $user, ?Carbon $endTime = null): TimeEntry
    {
        $activeTimer = TimeEntry::where('user_id', $user->id)
            ->whereNull('end_time')
            ->first();

        if (! $activeTimer) {
            throw new RuntimeException('No active timer found for this user.');
        }

        $endTime ??= Carbon::now('Africa/Cairo');
        $durationMinutes = (int) floor($activeTimer->start_time->diffInSeconds($endTime) / 60);

        $activeTimer->update([
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
        ]);

        broadcast(new TimerUpdated($user->id, 'stopped', null))->toOthers();

        return $activeTimer->fresh();
    }
}
