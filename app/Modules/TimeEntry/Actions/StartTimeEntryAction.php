<?php

namespace App\Modules\TimeEntry\Actions;

use App\Contracts\Trackable;
use App\Enums\TaskStatus;
use App\Events\TimerUpdated;
use App\Modules\Attendance\Actions\ClockInAction;
use App\Modules\Task\Models\Task;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

class StartTimeEntryAction
{
    public function __construct(
        private ClockInAction $clockInAction
    ) {}

    public function execute(User $user, Trackable $trackable, ?Carbon $startTime = null): TimeEntry
    {
        if ($this->activeTimerFor($user)) {
            throw new RuntimeException('User already has an active timer. Stop it before starting a new one.');
        }

        if ($trackable instanceof Task) {
            $this->validateTaskCanHaveTimer($trackable);
            $this->updateTaskOnTimerStart($trackable);
        }

        $startTime ??= Carbon::now('Africa/Cairo');
        $this->clockInAction->execute($user, $startTime);

        $timeEntry = TimeEntry::create([
            'user_id' => $user->id,
            'trackable_id' => $trackable->getKey(),
            'trackable_type' => get_class($trackable),
            'start_time' => $startTime,
        ]);

        broadcast(new TimerUpdated($user->id, 'started', $timeEntry))->toOthers();

        return $timeEntry;
    }

    private function activeTimerFor(User $user): ?TimeEntry
    {
        return TimeEntry::where('user_id', $user->id)
            ->whereNull('end_time')
            ->first();
    }

    private function validateTaskCanHaveTimer(Task $task): void
    {
        if (! Task::where('parent_id', $task->id)->exists()) {
            return;
        }

        $taskType = $task->type === 'main' ? 'Main Task' : 'Weekly Task';

        throw new InvalidArgumentException(
            "Cannot start timer on {$taskType} that has child tasks. ".
            'Please start the timer on a subtask instead.'
        );
    }

    private function updateTaskOnTimerStart(Task $task): void
    {
        $updates = [];

        if ($task->status === TaskStatus::TODO) {
            $updates['status'] = TaskStatus::IN_PROGRESS;
        }

        if ($task->start_date === null) {
            $updates['start_date'] = Carbon::now('Africa/Cairo')->toDateString();
        }

        if ($updates !== []) {
            $task->update($updates);
        }
    }
}
