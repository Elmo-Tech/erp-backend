<?php

namespace App\Modules\Dashboard\Actions;

use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Task\Models\Task;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\Settings\Models\User;
use Carbon\Carbon;

class GetEmployeeDashboardStatsAction
{
    public function execute(User $user): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $userId = $user->id;

        $myTotalTasks = Task::where('assigned_to', $userId)->count();
        $myCompletedTasks = Task::where('assigned_to', $userId)
            ->where('status', 'completed')
            ->count();
        $myInProgressTasks = Task::where('assigned_to', $userId)
            ->where('status', 'in_progress')
            ->count();
        $myTodoTasks = Task::where('assigned_to', $userId)
            ->where('status', 'todo')
            ->count();

        $myHoursThisMonth = (TimeEntry::where('user_id', $userId)
            ->where('start_time', '>=', $startOfMonth)
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $myHoursLastMonth = (TimeEntry::where('user_id', $userId)
            ->whereBetween('start_time', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $myHoursChange = $this->calculateChange($myHoursThisMonth, $myHoursLastMonth);

        $myProjectIds = Task::where('assigned_to', $userId)
            ->distinct('project_id')
            ->pluck('project_id');
        $myActiveProjects = Project::withoutGlobalScopes()
            ->whereIn('id', $myProjectIds)
            ->whereIn('status', [
                ProjectStatus::ACTIVE->value,
                ProjectStatus::UI_UX->value,
                ProjectStatus::DEVELOPMENT->value,
                ProjectStatus::TESTING->value,
            ])
            ->count();

        $todayAttendance = Attendance::where('user_id', $userId)
            ->whereDate('clock_in', Carbon::today())
            ->first();
        $clockedIn = $todayAttendance !== null;
        $todayHours = 0;
        if ($todayAttendance) {
            $todayHours = (TimeEntry::where('user_id', $userId)
                ->whereDate('start_time', Carbon::today())
                ->whereNotNull('end_time')
                ->sum('duration_minutes') ?? 0) / 60;
        }

        $myRecentTasks = Task::with('project')
            ->where('assigned_to', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit(4)
            ->get()
            ->map(fn ($task) => [
                'taskId' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'project' => $task->project?->title ?? 'No Project',
                'priority' => $task->priority ?? 'medium',
            ]);

        $myRecentActivities = $this->getRecentActivitiesForUser($userId);
        $pointsCycle = $this->getCurrentPointsCycleSummary($user);

        return [
            'role' => 'developer',
            'stats' => [
                'myTotalTasks' => $myTotalTasks,
                'myCompletedTasks' => $myCompletedTasks,
                'myInProgressTasks' => $myInProgressTasks,
                'myTodoTasks' => $myTodoTasks,
                'myHoursThisMonth' => round($myHoursThisMonth, 1),
                'myHoursChange' => $myHoursChange,
                'myActiveProjects' => $myActiveProjects,
                'clockedIn' => $clockedIn,
                'todayHours' => round($todayHours, 1),
            ],
            'pointsCycle' => $pointsCycle,
            'recentTasks' => $myRecentTasks,
            'recentActivities' => $myRecentActivities,
        ];
    }

    private function calculateChange(float|int $current, float|int $previous): float
    {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 1);
        }

        return $current > 0 ? 100 : 0;
    }

    private function getRecentActivitiesForUser(int $userId): array
    {
        $activities = collect();

        try {
            $recentTasks = Task::where('assigned_to', $userId)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn ($task) => [
                    'activityId' => $task->id,
                    'action' => 'Task: '.$task->title,
                    'user' => 'You',
                    'time' => $task->updated_at->diffForHumans(),
                    'type' => 'task',
                    'timestamp' => $task->updated_at,
                ]);
            $activities = $activities->merge($recentTasks);

            $recentTimeEntries = TimeEntry::where('user_id', $userId)
                ->whereNotNull('end_time')
                ->orderBy('end_time', 'desc')
                ->limit(3)
                ->get()
                ->map(fn ($entry) => [
                    'activityId' => $entry->id + 5000,
                    'action' => 'Logged '.round($entry->duration_minutes / 60, 1).'h of work',
                    'user' => 'You',
                    'time' => $entry->end_time->diffForHumans(),
                    'type' => 'time',
                    'timestamp' => $entry->end_time,
                ]);
            $activities = $activities->merge($recentTimeEntries);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Error fetching user activities: '.$e->getMessage());
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
