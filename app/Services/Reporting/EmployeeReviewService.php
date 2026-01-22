<?php

namespace App\Services\Reporting;

use App\Models\Attendance;
use App\Models\PointTransaction;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EmployeeReviewService
{
    /**
     * Get comprehensive employee review data.
     *
     * @param int $userId
     * @param array $filters ['dateFrom' => string, 'dateTo' => string]
     * @return array
     */
    public function getEmployeeReview(int $userId, array $filters = []): array
    {
        // Validate date range
        $this->validateDateRange($filters);

        $user = User::findOrFail($userId);

        // Get all data sections
        $overview = $this->getOverviewMetrics($user, $filters);
        $timeTracking = $this->getTimeTrackingData($user, $filters);
        $tasks = $this->getTasksData($user, $filters);
        $projects = $this->getProjectsData($user, $filters);
        $attendance = $this->getAttendanceData($user, $filters);
        $productivity = $this->getProductivityMetrics($user, $filters);
        $points = $this->getPointsData($user, $filters); // إضافة بيانات النقاط

        return [
            'employee' => [
                'userId' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'specialization' => $user->specialization,
                'totalPoints' => $user->total_points ?? 0, // إضافة النقاط الحالية
            ],
            'period' => [
                'dateFrom' => $filters['dateFrom'] ?? null,
                'dateTo' => $filters['dateTo'] ?? null,
            ],
            'overview' => $overview,
            'timeTracking' => $timeTracking,
            'tasks' => $tasks,
            'projects' => $projects,
            'attendance' => $attendance,
            'productivity' => $productivity,
            'points' => $points, // إضافة بيانات النقاط
        ];
    }

    /**
     * Get overview metrics.
     */
    private function getOverviewMetrics(User $user, array $filters): array
    {
        $totalTrackedMinutes = $this->calculateTotalTrackedTime($user, $filters);
        $totalIdleMinutes = $this->calculateTotalIdleTime($user, $filters);
        $taskCompletionRate = $this->calculateTaskCompletionRate($user, $filters);
        $averageBounceRate = $this->calculateAverageBounceRate($user, $filters);
        $attendanceDays = $this->countAttendanceDays($user, $filters);
        
        // حساب إحصائيات النقاط للفترة
        $pointsStats = $this->calculatePointsStats($user, $filters);

        return [
            'totalTrackedHours' => round($totalTrackedMinutes / 60, 2),
            'totalIdleHours' => round($totalIdleMinutes / 60, 2),
            'taskCompletionRate' => number_format($taskCompletionRate, 2, '.', ''),
            'averageBounceRate' => number_format($averageBounceRate, 2, '.', ''),
            'attendanceDays' => $attendanceDays,
            'productivityScore' => $this->calculateProductivityScore($taskCompletionRate, $averageBounceRate),
            // إضافة إحصائيات النقاط
            'pointsEarned' => $pointsStats['earned'],
            'pointsDeducted' => $pointsStats['deducted'],
            'netPointsChange' => $pointsStats['net'],
        ];
    }

    /**
     * Get time tracking data with breakdown.
     */
    private function getTimeTrackingData(User $user, array $filters): array
    {
        $query = TimeEntry::where('user_id', $user->id)
            ->whereNotNull('end_time')
            ->with(['trackable']);

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('start_time', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('start_time', '<=', $filters['dateTo']);
        }

        $timeEntries = $query->get();

        // Group by trackable type
        $byType = $timeEntries->groupBy('trackable_type')->map(function ($entries) {
            return [
                'count' => $entries->count(),
                'totalMinutes' => $entries->sum('duration_minutes'),
                'totalHours' => round($entries->sum('duration_minutes') / 60, 2),
            ];
        });

        // Group by date for chart
        $byDate = $timeEntries->groupBy(function ($entry) {
            return Carbon::parse($entry->start_time)->format('Y-m-d');
        })->map(function ($entries, $date) {
            return [
                'date' => $date,
                'totalMinutes' => $entries->sum('duration_minutes'),
                'totalHours' => round($entries->sum('duration_minutes') / 60, 2),
            ];
        })->values();

        return [
            'totalEntries' => $timeEntries->count(),
            'totalMinutes' => $timeEntries->sum('duration_minutes'),
            'totalHours' => round($timeEntries->sum('duration_minutes') / 60, 2),
            'byType' => $byType,
            'byDate' => $byDate,
        ];
    }

    /**
     * Get tasks data with details.
     */
    private function getTasksData(User $user, array $filters): array
    {
        $query = Task::where('assigned_to', $user->id)
            ->with(['project:id,title']);

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $tasks = $query->get();

        // Group by status
        $byStatus = $tasks->groupBy('status')->map(function ($tasks) {
            return $tasks->count();
        });

        // Group by priority
        $byPriority = $tasks->groupBy('priority')->map(function ($tasks) {
            return $tasks->count();
        });

        // Get tasks with bounces
        $tasksWithBounces = $tasks->where('reopen_count', '>', 0)->map(function ($task) {
            return [
                'taskId' => $task->id,
                'title' => $task->title,
                'projectTitle' => $task->project?->title,
                'status' => $task->status,
                'reopenCount' => $task->reopen_count,
                'estimatedHours' => $task->estimated_hours,
                'totalTimeSpent' => round($task->total_time_spent / 60, 2),
                // إضافة معلومات التسليم
                'dueDate' => $task->due_date?->format('Y-m-d'),
                'completedAt' => $task->completed_at?->format('Y-m-d\TH:i:s'),
                'delayDays' => $task->delay_days,
                'isOverdue' => $task->isOverdue(),
            ];
        })->values();

        // Recent tasks
        $recentTasks = $tasks->sortByDesc('updated_at')->take(10)->map(function ($task) {
            return [
                'taskId' => $task->id,
                'title' => $task->title,
                'projectTitle' => $task->project?->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'createdAt' => $task->created_at->format('Y-m-d'),
                'updatedAt' => $task->updated_at->format('Y-m-d'),
                // إضافة معلومات التسليم
                'dueDate' => $task->due_date?->format('Y-m-d'),
                'completedAt' => $task->completed_at?->format('Y-m-d\TH:i:s'),
                'delayDays' => $task->delay_days,
                'isOverdue' => $task->isOverdue(),
            ];
        })->values();

        return [
            'total' => $tasks->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'inProgress' => $tasks->where('status', 'in_progress')->count(),
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'tasksWithBounces' => $tasksWithBounces,
            'recentTasks' => $recentTasks,
            'totalBounces' => $tasks->sum('reopen_count'),
        ];
    }

    /**
     * Get projects data.
     */
    private function getProjectsData(User $user, array $filters): array
    {
        // Get projects where user is a member
        $query = Project::whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['client:id,name']);

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $projects = $query->get();

        $projectsList = $projects->map(function ($project) use ($user) {
            // Get user's time on this project
            $userTime = TimeEntry::where('user_id', $user->id)
                ->where('trackable_type', 'App\\Models\\Task')
                ->whereHas('trackable', function ($q) use ($project) {
                    $q->where('project_id', $project->id);
                })
                ->sum('duration_minutes');

            return [
                'projectId' => $project->id,
                'title' => $project->title,
                'clientName' => $project->client?->name,
                'status' => $project->status,
                'userTimeSpent' => round($userTime / 60, 2),
            ];
        })->values();

        return [
            'total' => $projects->count(),
            'active' => $projects->where('status', 'in_progress')->count(),
            'completed' => $projects->where('status', 'completed')->count(),
            'projects' => $projectsList,
        ];
    }

    /**
     * Get attendance data.
     */
    private function getAttendanceData(User $user, array $filters): array
    {
        $query = Attendance::where('user_id', $user->id);

        if (!empty($filters['dateFrom'])) {
            $query->where('date', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->where('date', '<=', $filters['dateTo']);
        }

        $attendances = $query->get();

        $attendanceList = $attendances->map(function ($attendance) {
            return [
                'date' => $attendance->date,
                'clockIn' => $attendance->clock_in,
                'clockOut' => $attendance->clock_out,
                'isLate' => $attendance->is_late,
                'lateMinutes' => $attendance->late_minutes,
                'idleMinutes' => $attendance->idle_minutes,
            ];
        })->values();

        return [
            'totalDays' => $attendances->count(),
            'lateDays' => $attendances->where('is_late', true)->count(),
            'totalLateMinutes' => $attendances->sum('late_minutes'),
            'totalIdleMinutes' => $attendances->sum('idle_minutes'),
            'averageIdlePerDay' => $attendances->count() > 0 
                ? round($attendances->sum('idle_minutes') / $attendances->count(), 2) 
                : 0,
            'attendanceList' => $attendanceList,
        ];
    }

    /**
     * Get productivity metrics.
     */
    private function getProductivityMetrics(User $user, array $filters): array
    {
        $totalTrackedMinutes = $this->calculateTotalTrackedTime($user, $filters);
        $totalIdleMinutes = $this->calculateTotalIdleTime($user, $filters);
        $taskCompletionRate = $this->calculateTaskCompletionRate($user, $filters);
        $averageBounceRate = $this->calculateAverageBounceRate($user, $filters);

        // Calculate efficiency (tracked time vs idle time)
        $totalWorkMinutes = $totalTrackedMinutes + $totalIdleMinutes;
        $efficiency = $totalWorkMinutes > 0 
            ? ($totalTrackedMinutes / $totalWorkMinutes) * 100 
            : 0;

        return [
            'efficiency' => number_format($efficiency, 2, '.', ''),
            'qualityScore' => number_format(100 - ($averageBounceRate * 100), 2, '.', ''),
            'completionRate' => number_format($taskCompletionRate, 2, '.', ''),
            'productivityScore' => $this->calculateProductivityScore($taskCompletionRate, $averageBounceRate),
        ];
    }

    /**
     * Calculate productivity score (0-100).
     */
    private function calculateProductivityScore(float $completionRate, float $bounceRate): string
    {
        // Weight: 70% completion rate, 30% quality (inverse of bounce rate)
        $qualityScore = max(0, 100 - ($bounceRate * 100));
        $score = ($completionRate * 0.7) + ($qualityScore * 0.3);
        
        return number_format($score, 2, '.', '');
    }

    /**
     * Calculate total tracked time in minutes.
     */
    private function calculateTotalTrackedTime(User $user, array $filters): int
    {
        $query = TimeEntry::where('user_id', $user->id)
            ->whereNotNull('end_time');

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('start_time', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('start_time', '<=', $filters['dateTo']);
        }

        return (int) $query->sum('duration_minutes');
    }

    /**
     * Calculate total idle time in minutes.
     */
    private function calculateTotalIdleTime(User $user, array $filters): int
    {
        $query = Attendance::where('user_id', $user->id);

        if (!empty($filters['dateFrom'])) {
            $query->where('date', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->where('date', '<=', $filters['dateTo']);
        }

        return (int) $query->sum('idle_minutes');
    }

    /**
     * Calculate task completion rate.
     */
    private function calculateTaskCompletionRate(User $user, array $filters): float
    {
        $query = Task::where('assigned_to', $user->id);

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $totalTasks = $query->count();

        if ($totalTasks === 0) {
            return 0.0;
        }

        $completedTasks = (clone $query)->where('status', 'completed')->count();

        return ($completedTasks / $totalTasks) * 100;
    }

    /**
     * Calculate average bounce rate.
     */
    private function calculateAverageBounceRate(User $user, array $filters): float
    {
        $query = Task::where('assigned_to', $user->id);

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $tasks = $query->get();

        if ($tasks->count() === 0) {
            return 0.0;
        }

        $totalBounces = $tasks->sum('reopen_count');

        return $totalBounces / $tasks->count();
    }

    /**
     * Count attendance days.
     */
    private function countAttendanceDays(User $user, array $filters): int
    {
        $query = Attendance::where('user_id', $user->id);

        if (!empty($filters['dateFrom'])) {
            $query->where('date', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->where('date', '<=', $filters['dateTo']);
        }

        return $query->count();
    }

    /**
     * Validate date range.
     */
    private function validateDateRange(array $filters): void
    {
        if (!empty($filters['dateFrom']) && !empty($filters['dateTo'])) {
            $dateFrom = Carbon::parse($filters['dateFrom']);
            $dateTo = Carbon::parse($filters['dateTo']);

            if ($dateFrom->gt($dateTo)) {
                throw new InvalidArgumentException('dateFrom cannot be greater than dateTo');
            }
        }
    }

    /**
     * Get points data for the user.
     */
    private function getPointsData(User $user, array $filters): array
    {
        $query = PointTransaction::where('user_id', $user->id);

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        // حساب الإحصائيات
        $pointsEarned = $transactions->where('points', '>', 0)->sum('points');
        $pointsDeducted = abs($transactions->where('points', '<', 0)->sum('points'));
        $netChange = $pointsEarned - $pointsDeducted;

        // تجميع المعاملات حسب الشهر
        $monthlyBreakdown = $transactions->groupBy(function ($transaction) {
            return Carbon::parse($transaction->created_at)->format('Y-m');
        })->map(function ($monthTransactions) {
            $earned = $monthTransactions->where('points', '>', 0)->sum('points');
            $deducted = abs($monthTransactions->where('points', '<', 0)->sum('points'));
            return [
                'earned' => $earned,
                'deducted' => $deducted,
                'net' => $earned - $deducted,
            ];
        });

        // تحويل المعاملات للعرض
        $transactionsList = $transactions->map(function ($transaction) {
            return [
                'transactionId' => $transaction->id,
                'points' => $transaction->points,
                'type' => $transaction->type->label(),
                'reason' => $transaction->reason ?? '',
                'createdAt' => $transaction->created_at->format('Y-m-d\TH:i:s'),
                'createdBy' => $transaction->createdBy?->name ?? 'System',
                'balanceAfter' => $transaction->balance_after,
            ];
        });

        return [
            'currentPoints' => $user->total_points ?? 0,
            'pointsEarned' => $pointsEarned,
            'pointsDeducted' => $pointsDeducted,
            'netChange' => $netChange,
            'transactions' => $transactionsList,
            'monthlyBreakdown' => $monthlyBreakdown,
        ];
    }

    /**
     * Calculate points statistics for overview.
     */
    private function calculatePointsStats(User $user, array $filters): array
    {
        $query = PointTransaction::where('user_id', $user->id);

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $transactions = $query->get();

        $earned = $transactions->where('points', '>', 0)->sum('points');
        $deducted = abs($transactions->where('points', '<', 0)->sum('points'));

        return [
            'earned' => $earned,
            'deducted' => $deducted,
            'net' => $earned - $deducted,
        ];
    }
}
