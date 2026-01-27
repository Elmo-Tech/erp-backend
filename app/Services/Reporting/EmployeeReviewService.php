<?php

namespace App\Services\Reporting;

use App\Models\Attendance;
use App\Models\PointTransaction;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Enums\PointTransactionType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EmployeeReviewService
{
    private function buildPointSignature(
        int $userId,
        int $points,
        
        $createdAt,
        ?string $transactionType,
        ?string $reason
    ): string {
        $timestamp = Carbon::parse($createdAt)->format('Y-m-d H:i');
        $normalizedReason = trim(strtolower($reason ?? ''));
        return implode('|', [$userId, $points, $transactionType ?? '', $timestamp, $normalizedReason]);
    }
    /**
     * Get comprehensive employee review data.
     *
     * @param int $userId
     * @param array $filters ['dateFrom' => string, 'dateTo' => string, 'page' => int, 'perPage' => int, 'forPdf' => bool]
     * @return array
     */
    public function getEmployeeReview(int $userId, array $filters = []): array
    {
        // Validate date range
        $this->validateDateRange($filters);

        $user = User::findOrFail($userId);

        // Use total_points from User model as the source of truth for current points
        $currentPoints = $user->total_points ?? 0;

        // Get all data sections
        $overview = $this->getOverviewMetrics($user, $filters);
        $timeTracking = $this->getTimeTrackingData($user, $filters);
        $tasks = $this->getTasksData($user, $filters);
        $projects = $this->getProjectsData($user, $filters);
        $attendance = $this->getAttendanceData($user, $filters);
        $productivity = $this->getProductivityMetrics($user, $filters);
        $points = $this->getPointsData($user, $filters);
        
        // إضافة معلومات أيام السماح والخصومات
        $gracePeriod = $this->getGracePeriodInfo($user, $filters);
        $deductions = $this->getDeductionInfo($user, $filters);
        
        // إضافة تحليلات متقدمة
        $taskPerformance = $this->getTaskPerformanceAnalysis($user, $filters);
        $qualityMetrics = $this->getQualityMetrics($user, $filters);
        $trends = $this->getProductivityTrends($user, $filters);

        return [
            'employee' => [
                'userId' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'specialization' => $user->specialization,
                'totalPoints' => $currentPoints,
                'salary' => $user->salary ?? 0, // إضافة الراتب الأساسي
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
            'points' => $points,
            'gracePeriod' => $gracePeriod,
            'deductions' => $deductions,
            'taskPerformance' => $taskPerformance,
            'qualityMetrics' => $qualityMetrics,
            'trends' => $trends,
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
     * Only includes tasks where the user is a PRIMARY assignee to ensure accurate performance metrics.
     */
    private function getTasksData(User $user, array $filters): array
    {
        // Get tasks assigned to user as PRIMARY assignee only
        $query = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->where('is_primary', true); // Only primary assignees
        })->with(['project:id,title']);

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
     * Calculate task completion rate for PRIMARY assignees only.
     * This ensures accurate performance metrics by only counting tasks the user is primarily responsible for.
     */
    private function calculateTaskCompletionRate(User $user, array $filters): float
    {
        $query = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->where('is_primary', true); // Only primary assignees
        });

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
     * Calculate average bounce rate for PRIMARY assignees only.
     * This ensures accurate quality metrics by only counting tasks the user is primarily responsible for.
     */
    private function calculateAverageBounceRate(User $user, array $filters): float
    {
        $query = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->where('is_primary', true); // Only primary assignees
        });

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
     * Get points data for the user from PointTransaction (single source of truth).
     * This method consolidates all point data from the unified PointTransaction system.
     * 
     * @param User $user
     * @param array $filters ['dateFrom', 'dateTo', 'page', 'perPage', 'forPdf']
     * @return array
     */
    private function getPointsData(User $user, array $filters): array
    {
        // Get automatic points from PointTransaction table
        $pointTransactionQuery = PointTransaction::where('user_id', $user->id);
        if (!empty($filters['dateFrom'])) {
            $pointTransactionQuery->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $pointTransactionQuery->whereDate('created_at', '<=', $filters['dateTo']);
        }
        
        // Get total count
        $totalCount = (clone $pointTransactionQuery)->count();
        
        // Check if this is for PDF generation (get all transactions)
        $forPdf = $filters['forPdf'] ?? false;
        
        if ($forPdf) {
            // For PDF: Get ALL transactions without pagination
            $pointTransactions = $pointTransactionQuery->with('createdBy')
                ->orderBy('created_at', 'desc')
                ->get();
            
            $page = 1;
            $perPage = $totalCount;
        } else {
            // For API: Apply pagination (default 50 per page)
            $page = $filters['page'] ?? 1;
            $perPage = $filters['perPage'] ?? 50;
            $pointTransactions = $pointTransactionQuery->with('createdBy')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // Consolidate all transactions into a unified format
        $allTransactions = collect();

        // Add PointTransactions (automatic system)
        foreach ($pointTransactions as $pt) {
            $allTransactions->push([
                'transactionId' => 'pt_' . $pt->id,
                'points' => $pt->points,
                'type' => $pt->type->label(),
                'reason' => $pt->reason ?? '',
                'createdAt' => $pt->created_at->format('Y-m-d\TH:i:s'),
                'createdBy' => $pt->createdBy?->name ?? 'System',
                'balanceAfter' => $pt->balance_after,
                'source' => 'automatic',
                'sourceType' => $pt->source_type,
                'sourceId' => $pt->source_id,
                'rawCreatedAt' => $pt->created_at,
            ]);
        }

        // Sort all transactions by creation date (newest first)
        $sortedTransactions = $allTransactions->sortByDesc('rawCreatedAt')->values();

        // Calculate statistics from ALL transactions in the period (not just current page)
        // For accurate stats, we need to query all transactions again
        $allPeriodTransactions = PointTransaction::where('user_id', $user->id);
        if (!empty($filters['dateFrom'])) {
            $allPeriodTransactions->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $allPeriodTransactions->whereDate('created_at', '<=', $filters['dateTo']);
        }
        $allPeriodTransactions = $allPeriodTransactions->get();
        
        $pointsEarned = $allPeriodTransactions->where('points', '>', 0)->sum('points');
        $pointsDeducted = abs($allPeriodTransactions->where('points', '<', 0)->sum('points'));
        $netChange = $pointsEarned - $pointsDeducted;

        // Monthly breakdown from all transactions in period
        $monthlyBreakdown = $allPeriodTransactions->groupBy(function ($transaction) {
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

        // Breakdown by source (automatic vs manual) based on type
        $automaticCount = $allPeriodTransactions->filter(fn($t) => !str_starts_with($t->type->label(), 'Manual'))->count();
        $manualCount = $allPeriodTransactions->filter(fn($t) => str_starts_with($t->type->label(), 'Manual'))->count();
        
        $bySource = [
            'automatic' => [
                'count' => $automaticCount,
                'earned' => $allPeriodTransactions->filter(fn($t) => !str_starts_with($t->type->label(), 'Manual'))->where('points', '>', 0)->sum('points'),
                'deducted' => abs($allPeriodTransactions->filter(fn($t) => !str_starts_with($t->type->label(), 'Manual'))->where('points', '<', 0)->sum('points')),
            ],
            'manual' => [
                'count' => $manualCount,
                'earned' => $allPeriodTransactions->filter(fn($t) => str_starts_with($t->type->label(), 'Manual'))->where('points', '>', 0)->sum('points'),
                'deducted' => abs($allPeriodTransactions->filter(fn($t) => str_starts_with($t->type->label(), 'Manual'))->where('points', '<', 0)->sum('points')),
            ],
        ];

        // Remove rawCreatedAt from final output
        $finalTransactions = $sortedTransactions->map(function ($transaction) {
            unset($transaction['rawCreatedAt']);
            return $transaction;
        });

        return [
            'currentPoints' => $user->total_points ?? 0,
            'pointsEarned' => $pointsEarned,
            'pointsDeducted' => $pointsDeducted,
            'netChange' => $netChange,
            'transactions' => $finalTransactions,
            'transactionsPagination' => [
                'total' => $totalCount,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $perPage),
                'showing' => $allTransactions->count(),
            ],
            'monthlyBreakdown' => $monthlyBreakdown,
            'bySource' => $bySource,
            'summary' => [
                'totalTransactions' => $totalCount,
                'automaticTransactions' => $automaticCount,
                'manualTransactions' => $manualCount,
            ],
        ];
    }

    /**
     * Calculate points statistics for overview from PointTransaction (single source of truth).
     */
    private function calculatePointsStats(User $user, array $filters): array
    {
        // Get from PointTransaction (automatic system)
        $pointTransactionQuery = PointTransaction::where('user_id', $user->id);
        if (!empty($filters['dateFrom'])) {
            $pointTransactionQuery->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $pointTransactionQuery->whereDate('created_at', '<=', $filters['dateTo']);
        }
        $pointTransactions = $pointTransactionQuery->get();

        // Calculate totals from PointTransaction only
        $totalEarned = $pointTransactions->where('points', '>', 0)->sum('points');
        $totalDeducted = abs($pointTransactions->where('points', '<', 0)->sum('points'));

        return [
            'earned' => $totalEarned,
            'deducted' => $totalDeducted,
            'net' => $totalEarned - $totalDeducted,
        ];
    }

    /**
     * Get grace period information for the employee.
     * Grace period is typically the number of days an employee can be late
     * before deductions start applying.
     */
    private function getGracePeriodInfo(User $user, array $filters): array
    {
        // Default grace period settings (can be moved to config or database)
        $gracePeriodDays = config('attendance.grace_period_days', 5); // 5 days default
        
        // Get attendance data for the period (use threshold-based lateness)
        $lateThreshold = (int) config('attendance.late_threshold_minutes', 15);
        $query = Attendance::where('user_id', $user->id)
            ->where('late_minutes', '>', $lateThreshold);

        if (!empty($filters['dateFrom'])) {
            $query->where('date', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->where('date', '<=', $filters['dateTo']);
        }

        $lateAttendances = $query->get();
        $lateDaysCount = $lateAttendances->count();
        
        // Calculate remaining grace days
        $remainingDays = max(0, $gracePeriodDays - $lateDaysCount);
        $isExpired = $lateDaysCount > $gracePeriodDays;

        // Get first late day to calculate when grace period started
        $firstLateDay = $lateAttendances->sortBy('date')->first();
        
        return [
            'allowedDays' => $gracePeriodDays,
            'daysUsed' => $lateDaysCount,
            'remainingDays' => $remainingDays,
            'isExpired' => $isExpired,
            'firstLateDate' => $firstLateDay?->date,
            'gracePeriodEndDate' => $firstLateDay ? 
                Carbon::parse($firstLateDay->date)->addDays($gracePeriodDays)->format('Y-m-d') : null,
            'daysOverLimit' => max(0, $lateDaysCount - $gracePeriodDays),
        ];
    }

    /**
     * Get deduction information for late attendance after grace period.
     */
    private function getDeductionInfo(User $user, array $filters): array
    {
        // Default deduction settings (can be moved to config or database)
        $gracePeriodDays = config('attendance.grace_period_days', 5);
        $deductionRatePerMinute = config('attendance.deduction_rate_per_minute', 0.1); // 0.1 points per minute
        
        // Get all late attendances based on threshold
        $lateThreshold = (int) config('attendance.late_threshold_minutes', 15);
        $query = Attendance::where('user_id', $user->id)
            ->where('late_minutes', '>', $lateThreshold);

        if (!empty($filters['dateFrom'])) {
            $query->where('date', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->where('date', '<=', $filters['dateTo']);
        }

        $lateAttendances = $query->orderBy('date')->get();
        
        // Skip grace period days and calculate deductions for remaining days
        $deductibleAttendances = $lateAttendances->skip($gracePeriodDays);
        
        $totalLateMinutes = $deductibleAttendances->sum('late_minutes');
        $totalDeductions = $totalLateMinutes * $deductionRatePerMinute;
        $daysWithDeductions = $deductibleAttendances->count();
        
        // Breakdown by day
        $dailyDeductions = $deductibleAttendances->map(function ($attendance) use ($deductionRatePerMinute) {
            $deduction = $attendance->late_minutes * $deductionRatePerMinute;
            return [
                'date' => $attendance->date,
                'lateMinutes' => $attendance->late_minutes,
                'deduction' => round($deduction, 2),
                'clockIn' => $attendance->clock_in,
            ];
        })->values();

        // Monthly breakdown
        $monthlyDeductions = $deductibleAttendances->groupBy(function ($attendance) {
            return Carbon::parse($attendance->date)->format('Y-m');
        })->map(function ($monthAttendances) use ($deductionRatePerMinute) {
            $monthLateMinutes = $monthAttendances->sum('late_minutes');
            $monthDeductions = $monthLateMinutes * $deductionRatePerMinute;
            return [
                'lateMinutes' => $monthLateMinutes,
                'deductions' => round($monthDeductions, 2),
                'lateDays' => $monthAttendances->count(),
            ];
        });

        return [
            'totalLateMinutes' => $totalLateMinutes,
            'deductionRate' => $deductionRatePerMinute,
            'totalDeductions' => round($totalDeductions, 2),
            'daysWithDeductions' => $daysWithDeductions,
            'dailyDeductions' => $dailyDeductions,
            'monthlyDeductions' => $monthlyDeductions,
            'gracePeriodDays' => $gracePeriodDays,
            'averageDeductionPerDay' => $daysWithDeductions > 0 ? 
                round($totalDeductions / $daysWithDeductions, 2) : 0,
        ];
    }

    /**
     * Get task delivery performance analysis.
     * Analyzes on-time vs late delivery rates and trends.
     */
    private function getTaskPerformanceAnalysis(User $user, array $filters): array
    {
        $query = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->where('is_primary', true);
        })->where('status', 'completed');

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('completed_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('completed_at', '<=', $filters['dateTo']);
        }

        $completedTasks = $query->get();
        
        if ($completedTasks->isEmpty()) {
            return [
                'totalCompleted' => 0,
                'onTimeCount' => 0,
                'lateCount' => 0,
                'onTimeRate' => 0,
                'averageDelayDays' => 0,
                'byMonth' => [],
            ];
        }

        $onTimeTasks = $completedTasks->filter(fn($task) => ($task->delay_days ?? 0) <= 0);
        $lateTasks = $completedTasks->filter(fn($task) => ($task->delay_days ?? 0) > 0);
        
        $totalCompleted = $completedTasks->count();
        $onTimeCount = $onTimeTasks->count();
        $lateCount = $lateTasks->count();
        $onTimeRate = $totalCompleted > 0 ? ($onTimeCount / $totalCompleted) * 100 : 0;
        
        // Calculate average delay for late tasks only
        $averageDelayDays = $lateCount > 0 
            ? $lateTasks->sum('delay_days') / $lateCount 
            : 0;

        // Monthly breakdown
        $byMonth = $completedTasks->groupBy(function ($task) {
            return Carbon::parse($task->completed_at)->format('Y-m');
        })->map(function ($monthTasks) {
            $monthOnTime = $monthTasks->filter(fn($task) => ($task->delay_days ?? 0) <= 0)->count();
            $monthLate = $monthTasks->filter(fn($task) => ($task->delay_days ?? 0) > 0)->count();
            $monthTotal = $monthTasks->count();
            
            return [
                'total' => $monthTotal,
                'onTime' => $monthOnTime,
                'late' => $monthLate,
                'onTimeRate' => $monthTotal > 0 ? ($monthOnTime / $monthTotal) * 100 : 0,
            ];
        });

        return [
            'totalCompleted' => $totalCompleted,
            'onTimeCount' => $onTimeCount,
            'lateCount' => $lateCount,
            'onTimeRate' => number_format($onTimeRate, 2, '.', ''),
            'averageDelayDays' => number_format($averageDelayDays, 2, '.', ''),
            'byMonth' => $byMonth,
        ];
    }

    /**
     * Get quality metrics including bug bounce rate and first-time pass rate.
     */
    private function getQualityMetrics(User $user, array $filters): array
    {
        $query = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->where('is_primary', true);
        });

        if (!empty($filters['dateFrom'])) {
            $query->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $query->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $tasks = $query->get();
        
        if ($tasks->isEmpty()) {
            return [
                'totalTasks' => 0,
                'tasksWithBounces' => 0,
                'firstTimePassRate' => 0,
                'averageBounceRate' => 0,
                'totalBounces' => 0,
            ];
        }

        $totalTasks = $tasks->count();
        $tasksWithBounces = $tasks->where('reopen_count', '>', 0)->count();
        $tasksWithoutBounces = $totalTasks - $tasksWithBounces;
        $firstTimePassRate = $totalTasks > 0 ? ($tasksWithoutBounces / $totalTasks) * 100 : 0;
        $totalBounces = $tasks->sum('reopen_count');
        $averageBounceRate = $totalTasks > 0 ? $totalBounces / $totalTasks : 0;

        return [
            'totalTasks' => $totalTasks,
            'tasksWithBounces' => $tasksWithBounces,
            'tasksWithoutBounces' => $tasksWithoutBounces,
            'firstTimePassRate' => number_format($firstTimePassRate, 2, '.', ''),
            'averageBounceRate' => number_format($averageBounceRate, 2, '.', ''),
            'totalBounces' => $totalBounces,
        ];
    }

    /**
     * Get productivity trends over time (weekly/monthly).
     */
    private function getProductivityTrends(User $user, array $filters): array
    {
        // Get time entries grouped by week
        $timeQuery = TimeEntry::where('user_id', $user->id)
            ->whereNotNull('end_time');

        if (!empty($filters['dateFrom'])) {
            $timeQuery->whereDate('start_time', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $timeQuery->whereDate('start_time', '<=', $filters['dateTo']);
        }

        $timeEntries = $timeQuery->get();

        // Group by week
        $weeklyData = $timeEntries->groupBy(function ($entry) {
            return Carbon::parse($entry->start_time)->format('Y-W');
        })->map(function ($weekEntries, $week) {
            return [
                'week' => $week,
                'totalHours' => round($weekEntries->sum('duration_minutes') / 60, 2),
                'entries' => $weekEntries->count(),
            ];
        })->values();

        // Get tasks completed grouped by week
        $taskQuery = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->where('is_primary', true);
        })->where('status', 'completed');

        if (!empty($filters['dateFrom'])) {
            $taskQuery->whereDate('completed_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $taskQuery->whereDate('completed_at', '<=', $filters['dateTo']);
        }

        $completedTasks = $taskQuery->get();

        $weeklyTasks = $completedTasks->groupBy(function ($task) {
            return Carbon::parse($task->completed_at)->format('Y-W');
        })->map(function ($weekTasks, $week) {
            return [
                'week' => $week,
                'completed' => $weekTasks->count(),
                'onTime' => $weekTasks->filter(fn($task) => ($task->delay_days ?? 0) <= 0)->count(),
            ];
        })->values();

        return [
            'weeklyTimeTracking' => $weeklyData,
            'weeklyTaskCompletion' => $weeklyTasks,
        ];
    }
}

