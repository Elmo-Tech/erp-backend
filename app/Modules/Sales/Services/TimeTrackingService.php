<?php

namespace App\Modules\Sales\Services;

use App\Contracts\Trackable;
use App\Enums\TaskStatus;
use App\Events\TimerUpdated;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Task\Models\Task;
use App\Modules\TimeEntry\Actions\StartTimeEntryAction;
use App\Modules\TimeEntry\Actions\StopTimeEntryAction;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\User\Models\User;
use App\Modules\Attendance\Actions\AttendanceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use RuntimeException;

class TimeTrackingService
{
    public function __construct(
        private ?AttendanceService $attendanceService = null,
        private ?StartTimeEntryAction $startTimeEntryAction = null,
        private ?StopTimeEntryAction $stopTimeEntryAction = null,
    ) {
        $this->attendanceService = $attendanceService ?? app(AttendanceService::class);
        $this->startTimeEntryAction = $startTimeEntryAction ?? app(StartTimeEntryAction::class);
        $this->stopTimeEntryAction = $stopTimeEntryAction ?? app(StopTimeEntryAction::class);
    }

    /**
     * Start a timer for a trackable entity.
     * Creates attendance record if first timer of the day.
     * Auto-updates task status from TODO to IN_PROGRESS and sets start_date.
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function startTimer(User $user, Trackable $trackable): TimeEntry
    {
        return $this->startTimeEntryAction->execute($user, $trackable);
    }

    /**
     * Validate that a task can have a timer started on it.
     * Timer is only allowed on leaf tasks (tasks with no children).
     *
     * @throws InvalidArgumentException
     */
    private function validateTaskCanHaveTimer(Task $task): void
    {
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
    private function updateTaskOnTimerStart(Task $task): void
    {
        $updates = [];

        // Auto-update status from TODO to IN_PROGRESS
        if ($task->status === TaskStatus::TODO) {
            $updates['status'] = TaskStatus::IN_PROGRESS;
        }

        // Set start_date to today if not already set
        if (is_null($task->start_date)) {
            $updates['start_date'] = Carbon::now('Africa/Cairo')->toDateString();
        }

        // Update task if there are changes
        if (! empty($updates)) {
            $task->update($updates);
        }
    }

    /**
     * Stop the active timer for a user.
     *
     * @throws RuntimeException
     */
    public function stopTimer(User $user): TimeEntry
    {
        return $this->stopTimeEntryAction->execute($user);
    }

    /**
     * Get the active timer for a user.
     */
    public function getActiveTimer(User $user): ?TimeEntry
    {
        return TimeEntry::where('user_id', $user->id)
            ->whereNull('end_time')
            ->first();
    }

    /**
     * Get time entries for a specific trackable entity.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTimeEntriesForTrackable(Trackable $trackable)
    {
        $trackableType = method_exists($trackable, 'getMorphClass')
            ? $trackable->getMorphClass()
            : get_class($trackable);

        return TimeEntry::where('trackable_id', $trackable->getKey())
            ->where('trackable_type', $trackableType)
            ->orderBy('start_time', 'desc')
            ->get();
    }

    /**
     * Get total time spent on a trackable entity in minutes.
     */
    public function getTotalTimeForTrackable(Trackable $trackable): int
    {
        $trackableType = method_exists($trackable, 'getMorphClass')
            ? $trackable->getMorphClass()
            : get_class($trackable);

        return TimeEntry::where('trackable_id', $trackable->getKey())
            ->where('trackable_type', $trackableType)
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
}
