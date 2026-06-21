<?php

namespace App\Modules\PersonalTasks\Actions;

use App\Modules\HR\Services\AttendanceService;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Facades\DB;

class TogglePersonalTaskTimerAction
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function execute(PersonalTask $task, User $user): PersonalTimeEntry
    {
        return DB::transaction(function () use ($task, $user) {
            $activeEntries = PersonalTimeEntry::query()
                ->where('personal_task_id', $task->id)
                ->where('user_id', $user->id)
                ->whereNull('end_time')
                ->lockForUpdate()
                ->get();

            if ($activeEntries->isNotEmpty()) {
                $stopped = null;

                foreach ($activeEntries as $entry) {
                    $entry->stop();
                    $stopped = $entry;
                }

                return $stopped->refresh();
            }

            $this->stopActiveProjectEntriesForUser($user);
            $this->stopActivePersonalEntriesForUser($user);
            $this->attendanceService->checkInForToday($user);

            return PersonalTimeEntry::create([
                'personal_task_id' => $task->id,
                'user_id' => $user->id,
                'start_time' => now(),
                'end_time' => null,
                'is_manual' => false,
            ]);
        });
    }

    public function stopActivePersonalEntriesForUser(User $user): void
    {
        $activeEntries = PersonalTimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNull('end_time')
            ->lockForUpdate()
            ->get();

        foreach ($activeEntries as $entry) {
            $entry->stop();
        }
    }

    public function stop(PersonalTimeEntry $entry): PersonalTimeEntry
    {
        return DB::transaction(function () use ($entry) {
            $entry = PersonalTimeEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $entry->stop();

            return $entry->refresh();
        });
    }

    private function stopActiveProjectEntriesForUser(User $user): void
    {
        $activeEntries = ProjectTimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNull('end_time')
            ->lockForUpdate()
            ->get();

        foreach ($activeEntries as $entry) {
            $entry->stop();
        }
    }
}
