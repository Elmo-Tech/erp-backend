<?php

namespace App\Services\Sales;

use App\Contracts\Trackable;
use App\Enums\TaskStatus;
use App\Enums\TimerStopReason;
use App\Events\TimerUpdated;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\HR\AttendanceService;
use App\Services\Tasks\ActiveWorkService;
use App\Services\Tasks\WorkActivityPublisher;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TimeTrackingService
{
    public function __construct(
        private ?AttendanceService $attendanceService = null
    ) {
        $this->attendanceService = $attendanceService ?? app(AttendanceService::class);
    }

    /**
     * Start a timer for a trackable entity.
     * Creates attendance record if first timer of the day.
     * Auto-updates task status from TODO to IN_PROGRESS and sets start_date.
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function startTimer(User $user, Trackable $trackable, ?int $resumedFromId = null): TimeEntry
    {
        $timeEntry = DB::transaction(function () use ($user, $trackable, $resumedFromId): TimeEntry {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($this->getActiveTimer($user)) {
                throw new RuntimeException('User already has an active timer. Stop it before starting a new one.');
            }

            if ($trackable instanceof Task) {
                $this->validateTaskCanHaveTimer($trackable);
                $this->updateTaskOnTimerStart($trackable, $resumedFromId !== null);
            }

            $this->ensureAttendanceRecord($user);
            $now = Carbon::now('Africa/Cairo');

            return TimeEntry::create([
                'user_id' => $user->id,
                'session_id' => (string) Str::uuid(),
                'trackable_id' => $trackable->getKey(),
                'trackable_type' => get_class($trackable),
                'start_time' => $now,
                'last_heartbeat_at' => $now,
                'resumed_from_id' => $resumedFromId,
            ]);
        });

        // Broadcast timer started event
        DB::afterCommit(fn () => $this->broadcastTimerUpdate($user->id, 'started', $timeEntry));
        app(WorkActivityPublisher::class)->changed('timer_started');

        return $timeEntry;
    }

    /**
     * Validate that a task can have a timer started on it.
     * Timer is only allowed on leaf tasks (tasks with no children).
     *
     * @throws InvalidArgumentException
     */
    private function validateTaskCanHaveTimer(Task $task): void
    {
        $target = app(ActiveWorkService::class)->taskTarget($task);
        if ($target['status'] !== 'available') {
            throw new InvalidArgumentException($target['message']);
        }
        // Check if task has any children
        $hasChildren = Task::where('parent_id', $task->id)->exists();

        if ($hasChildren) {
            $taskType = $task->type === 'main' ? 'Main Task' : 'Weekly Task';
            throw new InvalidArgumentException(
                "Cannot start timer on {$taskType} that has child tasks. ".
                'Please start the timer on a subtask instead.'
            );
        }
    }

    /**
     * Update task status and start_date when timer starts.
     * Changes status from TODO to IN_PROGRESS and sets start_date to today if not set.
     */
    private function updateTaskOnTimerStart(Task $task, bool $isResume): void
    {
        $updates = $this->pipelineStageUpdates($task, 'active');

        if ($task->is_pipeline_task || $task->status === TaskStatus::TODO || $isResume) {
            $updates['status'] = TaskStatus::IN_PROGRESS;
        }

        if (is_null($task->start_date)) {
            $updates['start_date'] = Carbon::now('Africa/Cairo')->toDateString();
        }

        if ($isResume || $task->is_pipeline_task) {
            $updates['hold_reason'] = null;
            $updates['completed_at'] = null;
        }

        if (! empty($updates)) {
            $task->update($updates);
        }
    }

    /**
     * Stop the active timer for a user.
     *
     * @throws RuntimeException
     */
    public function stopTimer(
        User $user,
        TimerStopReason $reason = TimerStopReason::END,
        ?Carbon $endTime = null
    ): TimeEntry {
        $activeTimer = $this->getActiveTimer($user);

        if (! $activeTimer) {
            throw new RuntimeException('No active timer found for this user.');
        }

        return $this->closeTimer(
            $activeTimer,
            $reason,
            $endTime ?? Carbon::now('Africa/Cairo')
        );
    }

    /**
     * End every open timer attached to a trackable (e.g. a task that was
     * completed or deleted) so no session keeps running on finished work.
     *
     * @return int number of timers that were closed
     */
    public function stopTimersForTrackable(Model $trackable, TimerStopReason $reason = TimerStopReason::END): int
    {
        $openEntries = TimeEntry::query()
            ->where('trackable_type', get_class($trackable))
            ->where('trackable_id', $trackable->getKey())
            ->whereNull('end_time')
            ->get();

        $closed = 0;

        foreach ($openEntries as $timeEntry) {
            $this->closeTimer($timeEntry, $reason, Carbon::now('Africa/Cairo'));
            $closed++;
        }

        return $closed;
    }

    /**
     * Get the active timer for a user.
     */
    public function getActiveTimer(User $user): ?TimeEntry
    {
        $activeTimer = TimeEntry::where('user_id', $user->id)->whereNull('end_time')
            ->first();
        $cutoff = now()->subSeconds((int) config('time_tracking.stale_after_seconds', 180));
        $heartbeatAt = $activeTimer?->heartbeatAt();

        if (
            config('time_tracking.expire_on_disconnect')
            && $activeTimer !== null
            && $heartbeatAt !== null
            && $heartbeatAt->lt($cutoff)
        ) {
            if ($this->expireStaleTimer($activeTimer->id, $cutoff)) {
                return null;
            }

            return TimeEntry::where('user_id', $user->id)
                ->whereNull('end_time')
                ->first();
        }

        return $activeTimer;
    }

    public function heartbeat(User $user, int $timeEntryId, string $sessionId): TimeEntry
    {
        $timeEntry = TimeEntry::query()
            ->whereKey($timeEntryId)
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->whereNull('end_time')
            ->first();

        if ($timeEntry === null) {
            throw new RuntimeException('The timer session is no longer active.');
        }

        $timeEntry->update(['last_heartbeat_at' => Carbon::now('Africa/Cairo')]);

        return $timeEntry->fresh();
    }

    public function pauseTimer(User $user): TimeEntry
    {
        return $this->stopTimer($user, TimerStopReason::PAUSE);
    }

    public function endTimer(User $user): TimeEntry
    {
        return DB::transaction(function () use ($user): TimeEntry {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $activeTimer = $this->getActiveTimer($user);
            if ($activeTimer === null) {
                throw new RuntimeException('This timer is already stopped or no longer available. Refresh to see the latest timer status.');
            }

            $target = app(ActiveWorkService::class)->target($activeTimer);
            $ended = $this->closeTimer($activeTimer, TimerStopReason::END, Carbon::now('Africa/Cairo'));

            if ($target['task'] instanceof Task && $target['status'] === 'available') {
                $task = Task::query()->whereKey($activeTimer->trackable_id)->lockForUpdate()->first();
                if ($task === null) {
                    return $ended;
                }
                $task->update([
                    ...$this->pipelineStageUpdates($task, 'done'),
                    'status' => TaskStatus::COMPLETED,
                    'completed_at' => now(),
                    'hold_reason' => null,
                    'updated_by' => $user->id,
                ]);
                $this->stopTimersForTrackable($task, TimerStopReason::END);
            }

            return $ended;
        });
    }

    public function holdTimer(User $user, string $reason): TimeEntry
    {
        $activeTimer = $this->getActiveTimer($user);

        if ($activeTimer === null) {
            throw new RuntimeException('No active timer found for this user.');
        }

        $target = app(ActiveWorkService::class)->target($activeTimer);
        if ($target['status'] !== 'available') {
            throw new InvalidArgumentException($target['message'].' End the timer to save its work time.');
        }
        $activeTimer->loadMissing('trackable');
        if (! $activeTimer->trackable instanceof Task) {
            throw new InvalidArgumentException('Hold is available only for project tasks.');
        }

        $task = $activeTimer->trackable;
        $task->update([
            ...$this->pipelineStageUpdates($task, 'paused'),
            'status' => TaskStatus::ON_HOLD,
            'hold_reason' => $reason,
        ]);

        return $this->closeTimer(
            $activeTimer,
            TimerStopReason::HOLD,
            Carbon::now('Africa/Cairo')
        );
    }

    public function resumeTimer(User $user, TimeEntry $previousEntry): TimeEntry
    {
        abort_unless($previousEntry->user_id === $user->id, 403);
        abort_if($previousEntry->end_time === null, 422);
        abort_unless(
            in_array($previousEntry->stopReason(), [
                TimerStopReason::PAUSE,
                TimerStopReason::HOLD,
                TimerStopReason::CONNECTION_LOST,
                TimerStopReason::AFTER_HOURS_TIMEOUT,
            ], true),
            422
        );

        $target = app(ActiveWorkService::class)->target($previousEntry);
        if ($target['status'] !== 'available') {
            throw new InvalidArgumentException($target['message']);
        }
        $previousEntry->loadMissing('trackable');
        if (! $previousEntry->trackable instanceof Trackable) {
            throw new InvalidArgumentException('The previous timer target is no longer available.');
        }

        if ($previousEntry->trackable instanceof Task) {
            $this->validateTaskCanHaveTimer($previousEntry->trackable);
        }

        return $this->startTimer($user, $previousEntry->trackable, $previousEntry->id);
    }

    public function getLatestResumableTimer(User $user): ?TimeEntry
    {
        $latestEntry = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('end_time')
            ->latest('end_time')
            ->first();

        if (
            $latestEntry === null
            || ! in_array($latestEntry->stopReason(), [
                TimerStopReason::PAUSE,
                TimerStopReason::HOLD,
                TimerStopReason::CONNECTION_LOST,
                TimerStopReason::AFTER_HOURS_TIMEOUT,
            ], true)
        ) {
            return null;
        }

        return app(ActiveWorkService::class)->target($latestEntry)['status'] === 'available'
            ? $latestEntry : null;
    }

    public function expireStaleTimers(): int
    {
        if (! config('time_tracking.expire_on_disconnect')) {
            return 0;
        }
        $cutoff = now()->subSeconds((int) config('time_tracking.stale_after_seconds', 180));
        $expiredCount = 0;

        TimeEntry::query()
            ->whereNull('end_time')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($entries) use (&$expiredCount, $cutoff): void {
                foreach ($entries as $entry) {
                    if ($this->expireStaleTimer($entry->id, $cutoff)) {
                        $expiredCount++;
                    }
                }
            });

        return $expiredCount;
    }

    /**
     * Get time entries for a specific trackable entity.
     *
     * @return Collection
     */
    public function getTimeEntriesForTrackable(Trackable $trackable)
    {
        return TimeEntry::where('trackable_id', $trackable->getKey())
            ->where('trackable_type', get_class($trackable))
            ->orderBy('start_time', 'desc')
            ->get();
    }

    /**
     * Get total time spent on a trackable entity in minutes.
     */
    public function getTotalTimeForTrackable(Trackable $trackable): int
    {
        return TimeEntry::where('trackable_id', $trackable->getKey())
            ->where('trackable_type', get_class($trackable))
            ->whereNotNull('end_time')
            ->sum('duration_minutes');
    }

    /**
     * Ensure attendance record exists for today.
     * Creates a new attendance record with clock_in if not exists.
     * Uses AttendanceService for proper deduction calculation.
     */
    private function ensureAttendanceRecord(User $user): Attendance
    {
        // Use AttendanceService for proper clock-in with deduction calculation
        return $this->attendanceService->clockIn($user);
    }

    /**
     * Clock out and calculate idle time for a user.
     * Uses AttendanceService for proper clock-out.
     *
     * @throws ModelNotFoundException
     */
    public function clockOut(User $user): Attendance
    {
        $attendance = $this->attendanceService->clockOut($user);

        if (! $attendance) {
            throw new ModelNotFoundException('No attendance record found for today.');
        }

        return $attendance;
    }

    public function closeTimer(
        TimeEntry $timeEntry,
        TimerStopReason $reason,
        Carbon $endTime
    ): TimeEntry {
        $durationMinutes = (int) floor($timeEntry->startedAt()->diffInSeconds($endTime) / 60);

        $timeEntry->update([
            'end_time' => $endTime,
            'duration_minutes' => max(0, $durationMinutes),
            'stop_reason' => $reason,
        ]);

        DB::afterCommit(
            fn () => $this->broadcastTimerUpdate($timeEntry->user_id, $reason->value)
        );
        app(WorkActivityPublisher::class)->changed('timer_'.$reason->value);

        return $timeEntry->fresh();
    }

    private function broadcastTimerUpdate(int $userId, string $action, ?TimeEntry $entry = null): void
    {
        try {
            broadcast(new TimerUpdated($userId, $action, $entry))->toOthers();
        } catch (Throwable $exception) {
            // Realtime delivery must not turn a committed timer change into a 500.
            Log::warning('Timer saved but realtime delivery failed', [
                'userId' => $userId, 'action' => $action, 'exception' => $exception,
            ]);
        }
    }

    private function pipelineStageUpdates(Task $task, string $category): array
    {
        if (! $task->is_pipeline_task) {
            return [];
        }

        $project = $task->project()->withoutGlobalScopes()->first();
        if (! $project instanceof Project) {
            return [];
        }

        $stage = $project->workflowStages()
            ->where('category', $category)
            ->first();

        if ($stage === null || $task->workflow_stage_id === $stage->id) {
            return [];
        }

        return [
            'workflow_stage_id' => $stage->id,
            'pipeline_position' => ((int) $stage->tasks()->max('pipeline_position')) + 1,
        ];
    }

    private function expireStaleTimer(int $timeEntryId, Carbon $cutoff): bool
    {
        return DB::transaction(function () use ($timeEntryId, $cutoff): bool {
            $timeEntry = TimeEntry::query()
                ->whereKey($timeEntryId)
                ->whereNull('end_time')
                ->where('last_heartbeat_at', '<', $cutoff)
                ->lockForUpdate()
                ->first();

            if ($timeEntry === null) {
                return false;
            }

            $heartbeatAt = $timeEntry->heartbeatAt();
            if ($heartbeatAt === null) {
                return false;
            }

            $this->closeTimer(
                $timeEntry,
                TimerStopReason::CONNECTION_LOST,
                TimerStopReason::AFTER_HOURS_TIMEOUT,
                $heartbeatAt
            );

            return true;
        });
    }
}
