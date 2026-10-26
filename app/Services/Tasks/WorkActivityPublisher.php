<?php

namespace App\Services\Tasks;

use App\Events\TimerUpdated;
use App\Events\WorkActivityChanged;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkActivityPublisher
{
    public function changed(string $reason, array $timerUserIds = []): void
    {
        DB::afterCommit(function () use ($reason, $timerUserIds): void {
            foreach (array_unique($timerUserIds) as $userId) {
                if ((int) $userId <= 0) {
                    continue;
                }
                try {
                    broadcast(new TimerUpdated((int) $userId, 'targets_changed'));
                } catch (Throwable $exception) {
                    Log::warning('Work changed but private timer synchronization could not be sent', ['exception' => $exception]);
                }
            }
            try {
                // Include the initiating browser too: not every change has a local
                // timer action (another employee's work, renamed/deleted tasks).
                broadcast(new WorkActivityChanged($reason));
            } catch (Throwable $exception) {
                Log::warning('Work changed but monitoring broadcast could not be sent', ['reason' => $reason, 'exception' => $exception]);
            }
        });
    }

    public function usersTrackingTasks(array $taskIds): array
    {
        return TimeEntry::where('trackable_type', Task::class)->whereIn('trackable_id', $taskIds)
            ->where(function ($query): void {
                $query->whereNull('end_time')->orWhereIn('stop_reason', ['pause', 'hold', 'connection_lost']);
            })->distinct()->pluck('user_id')->all();
    }
}
