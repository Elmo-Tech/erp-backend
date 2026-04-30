<?php

namespace App\Modules\Dashboard\Actions;

use App\Enums\TaskStatus;
use App\Modules\Task\Models\Task;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\Settings\Models\User;
use Carbon\Carbon;

class GetTeamStatsAction
{
    public function execute(): array
    {
        $employeeIds = User::query()
            ->employees()
            ->pluck('id');

        $taskStats = Task::query()
            ->whereIn('assigned_to', $employeeIds)
            ->selectRaw(
                'COUNT(*) as total,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as todo,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as in_progress,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as review,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as testing,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as completed',
                [
                    TaskStatus::TODO->value,
                    TaskStatus::IN_PROGRESS->value,
                    TaskStatus::REVIEW->value,
                    TaskStatus::TESTING->value,
                    TaskStatus::COMPLETED->value,
                ]
            )
            ->first();

        $hoursThisMonth = TimeEntry::query()
            ->whereIn('user_id', $employeeIds)
            ->where('start_time', '>=', Carbon::now()->startOfMonth())
            ->whereNotNull('end_time')
            ->sum('duration_minutes') / 60;

        $members = User::query()
            ->employees()
            ->whereIn('id', $employeeIds)
            ->orderByDesc('total_points')
            ->limit(8)
            ->get(['id', 'name', 'avatar', 'specialization', 'total_points'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->specialization?->label() ?? 'Team member',
                'avatar_url' => $user->avatar ?: null,
                'points' => (int) ($user->total_points ?? 0),
            ])
            ->all();

        return [
            'members' => $employeeIds->count(),
            'team_members' => $members,
            'hours_this_month' => round($hoursThisMonth, 1),
            'tasks' => [
                'total' => (int) $taskStats->total,
                'todo' => (int) $taskStats->todo,
                'in_progress' => (int) $taskStats->in_progress,
                'review' => (int) $taskStats->review,
                'testing' => (int) $taskStats->testing,
                'completed' => (int) $taskStats->completed,
            ],
        ];
    }
}
