<?php

namespace App\Modules\Dashboard\Actions;

use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use App\Modules\Task\Models\Task;
use App\Modules\TimeEntry\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GetManagerDashboardStatsAction
{
    public function execute(User $user): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $activeStatuses = [
            ProjectStatus::ACTIVE->value,
            ProjectStatus::UI_UX->value,
            ProjectStatus::DEVELOPMENT->value,
            ProjectStatus::TESTING->value,
        ];

        $teamMemberIds = User::role(['techdev', 'dev'])->pluck('id')->toArray();

        $activeProjects = Project::withoutGlobalScopes()
            ->whereIn('status', $activeStatuses)
            ->count();

        $totalTasks = Task::whereIn('assigned_to', $teamMemberIds)->count();
        $completedTasks = Task::whereIn('assigned_to', $teamMemberIds)
            ->where('status', 'completed')
            ->count();
        $pendingTasks = Task::whereIn('assigned_to', $teamMemberIds)
            ->whereIn('status', ['todo', 'in_progress'])
            ->count();

        $teamHoursThisMonth = (TimeEntry::whereIn('user_id', $teamMemberIds)
            ->where('start_time', '>=', $startOfMonth)
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $teamHoursLastMonth = (TimeEntry::whereIn('user_id', $teamMemberIds)
            ->whereBetween('start_time', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $teamHoursChange = $this->calculateChange($teamHoursThisMonth, $teamHoursLastMonth);

        $teamMembersCount = count($teamMemberIds);

        $recentTasks = Task::with(['assignee', 'project'])
            ->whereIn('assigned_to', $teamMemberIds)
            ->orderBy('updated_at', 'desc')
            ->limit(4)
            ->get()
            ->map(fn ($task) => [
                'taskId' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'assignee' => $task->assignee?->name ?? 'Unassigned',
                'project' => $task->project?->title ?? 'No Project',
            ]);

        $recentActivities = $this->getRecentActivitiesForTeam($teamMemberIds);
        $pointsCycle = $this->getCurrentPointsCycleSummary($user);

        return [
            'role' => 'pmo',
            'stats' => [
                'activeProjects' => $activeProjects,
                'totalTasks' => $totalTasks,
                'completedTasks' => $completedTasks,
                'pendingTasks' => $pendingTasks,
                'teamHours' => round($teamHoursThisMonth),
                'teamHoursChange' => $teamHoursChange,
                'teamMembers' => $teamMembersCount,
            ],
            'pointsCycle' => $pointsCycle,
            'recentTasks' => $recentTasks,
            'recentActivities' => $recentActivities,
        ];
    }

    private function calculateChange(float|int $current, float|int $previous): float
    {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 1);
        }

        return $current > 0 ? 100 : 0;
    }

    private function getRecentActivitiesForTeam(array $teamMemberIds): array
    {
        $activities = collect();

        try {
            $recentTasks = Task::with('assignee')
                ->whereIn('assigned_to', $teamMemberIds)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn ($task) => [
                    'activityId' => $task->id,
                    'action' => 'Task updated: '.$task->title,
                    'user' => $task->assignee?->name ?? 'System',
                    'time' => $task->updated_at->diffForHumans(),
                    'type' => 'task',
                    'timestamp' => $task->updated_at,
                ]);
            $activities = $activities->merge($recentTasks);
        } catch (\Exception $e) {
            Log::warning('Error fetching team activities: '.$e->getMessage());
        }

        return $activities
            ->sortByDesc('timestamp')
            ->take(4)
            ->map(fn ($activity) => collect($activity)->except('timestamp')->toArray())
            ->values()
            ->toArray();
    }

    private function getCurrentPointsCycleSummary(User $user): array
    {
        $now = Carbon::now();
        $cycleStartMonth = $now->month % 2 === 1 ? $now->month : $now->month - 1;

        $startDate = Carbon::create($now->year, $cycleStartMonth, 1)->startOfMonth();
        $endDate = $startDate->copy()->addMonth()->endOfMonth();

        $transactions = PointTransaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $earnedPoints = (int) $transactions->where('points', '>', 0)->sum('points');
        $deductedPoints = (int) abs($transactions->where('points', '<', 0)->sum('points'));

        return [
            'label' => sprintf(
                '%s-%s %s',
                $startDate->translatedFormat('M'),
                $endDate->translatedFormat('M'),
                $endDate->translatedFormat('Y')
            ),
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'netPoints' => $earnedPoints - $deductedPoints,
            'earnedPoints' => $earnedPoints,
            'deductedPoints' => $deductedPoints,
            'transactionCount' => $transactions->count(),
        ];
    }
}
