<?php

namespace App\Modules\Project\Actions;

use App\Modules\HR\Services\AttendanceService;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Starts or stops the timer for a given task + user pair.
 *
 * State machine:
 *   • No active entry  →  START: creates a new ProjectTimeEntry (end_time = null).
 *   • Active entry found →  STOP:  sets end_time = now(), computes duration_minutes.
 *
 * "Active" is defined as: a ProjectTimeEntry for this (task, user) pair
 * where end_time IS NULL.
 *
 * Only one active entry per (task, user) is allowed. If somehow multiple
 * orphaned open entries exist (e.g. from a crash), all are closed before
 * a new one is started — this is a safety net, not normal flow.
 */
class ToggleTaskTimerAction
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * @param  ProjectTask  $task  The task to toggle the timer on.
     * @param  User  $user  The developer toggling the timer.
     * @return ProjectTimeEntry The stopped entry (STOP) or the new entry (START).
     */
    public function execute(ProjectTask $task, User $user): ProjectTimeEntry
    {
        return DB::transaction(function () use ($task, $user) {
            // Lock the rows so concurrent requests for the same (task, user)
            // cannot both see "no active entry" and create duplicates.
            $activeEntries = ProjectTimeEntry::where('task_id', $task->id)
                ->where('user_id', $user->id)
                ->whereNull('end_time')
                ->lockForUpdate()
                ->get();

            // ── STOP ─────────────────────────────────────────────────────────
            if ($activeEntries->isNotEmpty()) {
                $now = now();

                // Close all open entries (safety net for orphaned rows).
                // The last one in the collection is returned as the canonical
                // stopped entry.
                $stopped = null;

                foreach ($activeEntries as $entry) {
                    $entry->update([
                        'end_time' => $now,
                        'duration_minutes' => (int) $entry->start_time->diffInMinutes($now),
                    ]);

                    $stopped = $entry;
                }

                return $stopped->refresh();
            }

            // ── START ─────────────────────────────────────────────────────────
            $this->stopActiveEntriesForUser($user);
            $this->stopActivePersonalEntriesForUser($user);

            // Fire auto check-in BEFORE creating the time entry. The service is
            // idempotent, so existing attendance never blocks timer starts.
            $this->attendanceService->checkInForToday($user);
            $this->moveTaskIntoProgressWhenStarting($task);

            return ProjectTimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'start_time' => now(),
                'end_time' => null,
                'is_manual' => false,
            ]);
        });
    }

    public function startForUser(ProjectTask $task, User $user): ProjectTimeEntry
    {
        return DB::transaction(function () use ($task, $user) {
            $this->stopActiveEntriesForUser($user);
            $this->stopActivePersonalEntriesForUser($user);
            $this->attendanceService->checkInForToday($user);
            $this->moveTaskIntoProgressWhenStarting($task);

            return ProjectTimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'start_time' => now(),
                'end_time' => null,
                'is_manual' => false,
            ]);
        });
    }

    public function stop(ProjectTimeEntry $entry): ProjectTimeEntry
    {
        return DB::transaction(function () use ($entry) {
            $entry = ProjectTimeEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $entry->stop();

            return $entry->refresh();
        });
    }

    private function stopActiveEntriesForUser(User $user): void
    {
        $activeEntries = ProjectTimeEntry::where('user_id', $user->id)
            ->whereNull('end_time')
            ->lockForUpdate()
            ->get();

        $now = now();

        foreach ($activeEntries as $entry) {
            $entry->update([
                'end_time' => $now,
                'duration_minutes' => (int) $entry->start_time->diffInMinutes($now),
            ]);
        }
    }

    private function stopActivePersonalEntriesForUser(User $user): void
    {
        $activeEntries = PersonalTimeEntry::where('user_id', $user->id)
            ->whereNull('end_time')
            ->lockForUpdate()
            ->get();

        foreach ($activeEntries as $entry) {
            $entry->stop();
        }
    }

    private function moveTaskIntoProgressWhenStarting(ProjectTask $task): void
    {
        if (! in_array($task->status, [ExecutionTaskStatus::TO_DO, ExecutionTaskStatus::BACKLOG], true)) {
            return;
        }

        $task->forceFill(['status' => ExecutionTaskStatus::IN_PROGRESS])->save();
    }
}
