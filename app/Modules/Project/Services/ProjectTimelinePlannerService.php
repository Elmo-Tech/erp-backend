<?php

namespace App\Modules\Project\Services;

use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Enums\ProjectRole;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProjectTimelinePlannerService
{
    /**
     * @param  array<int>  $projectIds
     * @param  array<int>  $userIds
     * @return array<string, mixed>
     */
    public function build(Carbon $startDate, Carbon $endDate, User $viewer, array $projectIds = [], array $userIds = []): array
    {
        $dates = $this->dateStrings($startDate, $endDate);

        $tasks = ProjectTask::query()
            ->with([
                'assignee.employeeProfile.shift',
                'project:id,title,deadline',
                'project.members.user.employeeProfile.shift',
            ])
            ->whereNotNull('assignee_id')
            ->where('status', '!=', ExecutionTaskStatus::DONE->value)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($span) use ($startDate, $endDate) {
                        $span->whereDate('start_date', '<=', $startDate->toDateString())
                            ->whereDate('due_date', '>=', $endDate->toDateString());
                    });
            })
            ->when($projectIds !== [], fn ($query) => $query->whereIn('project_id', $projectIds))
            ->when($userIds !== [], fn ($query) => $query->whereIn('assignee_id', $userIds))
            ->when(! $this->canViewAllTimeline($viewer), function ($query) use ($viewer) {
                $managerProjectIds = ProjectMember::query()
                    ->where('user_id', $viewer->id)
                    ->whereIn('role', [ProjectRole::MANAGER->value, ProjectRole::TECH_LEAD->value])
                    ->pluck('project_id')
                    ->all();

                $query->where(function ($visible) use ($viewer, $managerProjectIds) {
                    $visible->where('assignee_id', $viewer->id);

                    if ($managerProjectIds !== []) {
                        $visible->orWhereIn('project_id', $managerProjectIds);
                    }
                });
            })
            ->orderBy('start_date')
            ->orderBy('due_date')
            ->get();

        $users = $this->timelineUsers($tasks, $userIds);
        $capacityByUser = $users->mapWithKeys(fn (User $user) => [
            $user->id => $this->capacityByDate($user, $dates),
        ])->all();

        $allocationByUserDate = [];
        $items = $tasks->map(function (ProjectTask $task) use ($dates, &$allocationByUserDate) {
            $allocated = $this->allocatedHoursByDate($task, $dates);

            foreach ($allocated as $date => $hours) {
                if ($hours <= 0 || $task->assignee_id === null) {
                    continue;
                }

                $allocationByUserDate[$task->assignee_id][$date]['hours'] = round(
                    ($allocationByUserDate[$task->assignee_id][$date]['hours'] ?? 0) + $hours,
                    2
                );
                $allocationByUserDate[$task->assignee_id][$date]['taskIds'][] = $task->id;
            }

            return [
                'taskId' => $task->id,
                'projectId' => $task->project_id,
                'projectTitle' => $task->project?->title,
                'title' => $task->title,
                'assigneeId' => $task->assignee_id,
                'startDate' => $this->taskStart($task)?->toDateString(),
                'dueDate' => $this->taskDue($task)?->toDateString(),
                'estimatedHours' => $task->estimated_hours !== null ? (float) $task->estimated_hours : null,
                'status' => $task->status?->value,
                'allocatedHoursByDate' => $allocated,
            ];
        })->values()->all();

        $conflicts = $this->conflicts($allocationByUserDate, $capacityByUser);
        $suggestions = $this->suggestions($tasks, $conflicts, $capacityByUser, $allocationByUserDate, $dates);

        return [
            'range' => [
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
            ],
            'users' => $users->map(fn (User $user) => [
                'userId' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'dailyCapacityHoursByDate' => $capacityByUser[$user->id] ?? $this->zeroHours($dates),
            ])->values()->all(),
            'items' => $items,
            'conflicts' => $conflicts,
            'suggestions' => $suggestions,
        ];
    }

    private function canViewAllTimeline(User $viewer): bool
    {
        return $viewer->can('manage_all_projects')
            || $viewer->can('view_all_projects')
            || $viewer->can('manage_tasks');
    }

    /**
     * @param  Collection<int, ProjectTask>  $tasks
     * @param  array<int>  $userIds
     * @return Collection<int, User>
     */
    private function timelineUsers(Collection $tasks, array $userIds): Collection
    {
        $users = $tasks
            ->pluck('assignee')
            ->filter()
            ->keyBy('id');

        if ($userIds !== []) {
            User::query()
                ->with('employeeProfile.shift')
                ->whereIn('id', $userIds)
                ->get()
                ->each(fn (User $user) => $users->put($user->id, $user));
        }

        return $users->sortBy('name')->values();
    }

    /**
     * @param  array<int, string>  $dates
     * @return array<string, float>
     */
    private function capacityByDate(User $user, array $dates): array
    {
        $shift = $user->employeeProfile?->shift;
        $workingDays = $shift?->working_days ?: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'];
        $hours = $shift ? $this->shiftHours($shift->start_time, $shift->end_time) : 8.0;

        $capacity = [];
        foreach ($dates as $date) {
            $capacity[$date] = in_array(Carbon::parse($date)->format('D'), $workingDays, true)
                ? round($hours, 2)
                : 0.0;
        }

        return $capacity;
    }

    private function shiftHours(string $start, string $end): float
    {
        $startTime = Carbon::parse($start);
        $endTime = Carbon::parse($end);

        if ($endTime->lessThanOrEqualTo($startTime)) {
            $endTime->addDay();
        }

        return max(0, $startTime->floatDiffInHours($endTime));
    }

    /**
     * @param  array<int, string>  $dates
     * @return array<string, float>
     */
    private function allocatedHoursByDate(ProjectTask $task, array $dates): array
    {
        $hours = (float) ($task->estimated_hours ?? 0);
        $allocated = $this->zeroHours($dates);
        $start = $this->taskStart($task);
        $due = $this->taskDue($task);

        if ($hours <= 0 || $start === null || $due === null || $task->assignee === null) {
            return $allocated;
        }

        if ($due->lessThan($start)) {
            [$start, $due] = [$due, $start];
        }

        $taskDates = collect($this->dateStrings($start, $due))
            ->filter(fn (string $date) => array_key_exists($date, $allocated))
            ->filter(fn (string $date) => ($this->capacityByDate($task->assignee, [$date])[$date] ?? 0) > 0)
            ->values();

        if ($taskDates->isEmpty()) {
            return $allocated;
        }

        $perDay = round($hours / $taskDates->count(), 2);
        $remaining = $hours;

        foreach ($taskDates as $index => $date) {
            $allocated[$date] = $index === $taskDates->count() - 1
                ? round($remaining, 2)
                : $perDay;
            $remaining -= $allocated[$date];
        }

        return $allocated;
    }

    /**
     * @param  array<int, array<string, array{hours: float, taskIds: array<int>}>  $allocationByUserDate
     * @param  array<int, array<string, float>>  $capacityByUser
     * @return array<int, array<string, mixed>>
     */
    private function conflicts(array $allocationByUserDate, array $capacityByUser): array
    {
        $conflicts = [];

        foreach ($allocationByUserDate as $userId => $byDate) {
            foreach ($byDate as $date => $allocation) {
                $allocatedHours = round((float) $allocation['hours'], 2);
                $capacityHours = round((float) ($capacityByUser[$userId][$date] ?? 0), 2);

                if ($allocatedHours > $capacityHours) {
                    $conflicts[] = [
                        'userId' => (int) $userId,
                        'date' => $date,
                        'capacityHours' => $capacityHours,
                        'allocatedHours' => $allocatedHours,
                        'overloadHours' => round($allocatedHours - $capacityHours, 2),
                        'taskIds' => array_values(array_unique($allocation['taskIds'])),
                    ];
                }
            }
        }

        usort($conflicts, fn (array $a, array $b) => [$a['date'], $a['userId']] <=> [$b['date'], $b['userId']]);

        return $conflicts;
    }

    /**
     * @param  Collection<int, ProjectTask>  $tasks
     * @param  array<int, array<string, mixed>>  $conflicts
     * @param  array<int, array<string, float>>  $capacityByUser
     * @param  array<int, array<string, array{hours: float, taskIds: array<int>}>  $allocationByUserDate
     * @param  array<int, string>  $dates
     * @return array<int, array<string, mixed>>
     */
    private function suggestions(Collection $tasks, array $conflicts, array $capacityByUser, array $allocationByUserDate, array $dates): array
    {
        $suggestions = [];
        $tasksById = $tasks->keyBy('id');

        foreach ($conflicts as $conflict) {
            $task = collect($conflict['taskIds'])
                ->map(fn (int $taskId) => $tasksById->get($taskId))
                ->filter()
                ->sortByDesc(fn (ProjectTask $candidate) => (float) ($candidate->estimated_hours ?? 0))
                ->first();

            if (! $task instanceof ProjectTask) {
                continue;
            }

            $reschedule = $this->rescheduleSuggestion($task, $capacityByUser, $allocationByUserDate);
            if ($reschedule !== null) {
                $suggestions[] = $reschedule;
            }

            $reassign = $this->reassignSuggestion($task, $capacityByUser, $allocationByUserDate, $dates);
            if ($reassign !== null) {
                $suggestions[] = $reassign;
            }
        }

        return collect($suggestions)
            ->unique(fn (array $suggestion) => $suggestion['type'].'-'.$suggestion['taskId'])
            ->values()
            ->all();
    }

    private function rescheduleSuggestion(ProjectTask $task, array $capacityByUser, array $allocationByUserDate): ?array
    {
        $assignee = $task->assignee;
        $start = $this->taskStart($task);
        $due = $this->taskDue($task);
        $deadline = $task->project?->deadline ? Carbon::parse($task->project->deadline) : $due?->copy()->addDays(14);

        if ($assignee === null || $start === null || $due === null || $deadline === null) {
            return null;
        }

        $hours = (float) ($task->estimated_hours ?? 0);
        $cursor = $due->copy()->addDay();

        while ($cursor->lessThanOrEqualTo($deadline)) {
            $candidateEnd = $cursor->copy();
            while ($candidateEnd->lessThanOrEqualTo($deadline)) {
                $candidateDates = $this->dateStrings($cursor, $candidateEnd);
                $workingDates = collect($candidateDates)
                    ->filter(fn (string $date) => ($this->capacityByDate($assignee, [$date])[$date] ?? 0) > 0)
                    ->values();

                if ($workingDates->isNotEmpty()) {
                    $perDay = $hours / $workingDates->count();
                    $fits = $workingDates->every(function (string $date) use ($task, $perDay, $capacityByUser, $allocationByUserDate) {
                        $capacity = $capacityByUser[$task->assignee_id][$date] ?? $this->capacityByDate($task->assignee, [$date])[$date] ?? 0;
                        $current = $allocationByUserDate[$task->assignee_id][$date]['hours'] ?? 0;

                        return $current + $perDay <= $capacity;
                    });

                    if (! $fits) {
                        $candidateEnd->addDay();
                        continue;
                    }

                    return [
                        'type' => 'reschedule',
                        'risk' => $candidateEnd->greaterThan($due) ? 'medium' : 'low',
                        'taskId' => $task->id,
                        'reason' => 'Move this task to the nearest available working window for the current assignee.',
                        'current' => [
                            'startDate' => $start->toDateString(),
                            'dueDate' => $due->toDateString(),
                            'assigneeId' => $task->assignee_id,
                        ],
                        'suggested' => [
                            'startDate' => $cursor->toDateString(),
                            'dueDate' => $candidateEnd->toDateString(),
                            'assigneeId' => $task->assignee_id,
                        ],
                    ];
                }

                $candidateEnd->addDay();
            }

            $cursor->addDay();
        }

        return null;
    }

    /**
     * @param  array<int, string>  $dates
     */
    private function reassignSuggestion(ProjectTask $task, array $capacityByUser, array $allocationByUserDate, array $dates): ?array
    {
        $start = $this->taskStart($task);
        $due = $this->taskDue($task);

        if ($start === null || $due === null || $task->relationLoaded('project') === false) {
            return null;
        }

        $taskDates = collect($this->dateStrings($start, $due))
            ->filter(fn (string $date) => in_array($date, $dates, true))
            ->values();

        if ($taskDates->isEmpty()) {
            return null;
        }

        foreach ($task->project->members as $member) {
            $candidate = $member->user;
            if (! $candidate instanceof User || $candidate->id === $task->assignee_id) {
                continue;
            }

            $candidateCapacity = $capacityByUser[$candidate->id] ?? $this->capacityByDate($candidate, $dates);
            $workingDates = $taskDates
                ->filter(fn (string $date) => ($candidateCapacity[$date] ?? 0) > 0)
                ->values();

            if ($workingDates->isEmpty()) {
                continue;
            }

            $perDay = ((float) ($task->estimated_hours ?? 0)) / $workingDates->count();
            $fits = $workingDates->every(function (string $date) use ($candidate, $candidateCapacity, $allocationByUserDate, $perDay) {
                $current = $allocationByUserDate[$candidate->id][$date]['hours'] ?? 0;

                return $current + $perDay <= ($candidateCapacity[$date] ?? 0);
            });

            if ($fits) {
                return [
                    'type' => 'reassign',
                    'risk' => 'low',
                    'taskId' => $task->id,
                    'reason' => 'Assign this task to an available member on the same project.',
                    'current' => [
                        'startDate' => $start->toDateString(),
                        'dueDate' => $due->toDateString(),
                        'assigneeId' => $task->assignee_id,
                    ],
                    'suggested' => [
                        'startDate' => $start->toDateString(),
                        'dueDate' => $due->toDateString(),
                        'assigneeId' => $candidate->id,
                        'assigneeName' => $candidate->name,
                    ],
                ];
            }
        }

        return null;
    }

    private function taskStart(ProjectTask $task): ?Carbon
    {
        if ($task->start_date !== null) {
            return Carbon::parse($task->start_date)->startOfDay();
        }

        return $task->due_date !== null ? Carbon::parse($task->due_date)->startOfDay() : null;
    }

    private function taskDue(ProjectTask $task): ?Carbon
    {
        if ($task->due_date !== null) {
            return Carbon::parse($task->due_date)->startOfDay();
        }

        return $task->start_date !== null ? Carbon::parse($task->start_date)->startOfDay() : null;
    }

    /**
     * @return array<int, string>
     */
    private function dateStrings(Carbon $startDate, Carbon $endDate): array
    {
        $dates = [];
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param  array<int, string>  $dates
     * @return array<string, float>
     */
    private function zeroHours(array $dates): array
    {
        return array_fill_keys($dates, 0.0);
    }
}
