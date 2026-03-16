<?php

namespace App\Services\Reporting;

use App\Models\Attendance;
use App\Models\PointTransaction;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Enums\PointTransactionType;
use App\Enums\TaskStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EmployeeReviewService
{
    /**
     * Cache filtered primary tasks per request to keep the reporting sections aligned.
     *
     * @var array<string, \Illuminate\Support\Collection<int, \App\Models\Task>>
     */
    private array $primaryTasksCache = [];

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

        // Generate project breakdown for Blade template
        $projectBreakdown = $projects['projects'] ?? [];
        
        // Generate weekly statistics for Blade template
        $weeklyStats = $this->getWeeklyStatistics($user, $filters);

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
            'projects' => $projects, // Use the correct projects data structure
            'attendance' => $attendance,
            'productivity' => $productivity,
            'points' => $points,
            'gracePeriod' => $gracePeriod,
            'deductions' => $deductions,
            'taskPerformance' => $taskPerformance,
            'qualityMetrics' => $qualityMetrics,
            'trends' => $trends,
            'weeklyStats' => $weeklyStats, // Added for Blade template
            'projectBreakdown' => $projectBreakdown, // Keep this for Blade template
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
        $tasks = $this->getPrimaryTasksForPeriod($user, $filters);

        $byStatus = $tasks->groupBy(fn (Task $task) => $this->getTaskStatusValue($task) ?? 'unknown')
            ->map(fn (Collection $groupedTasks) => $groupedTasks->count());

        $byPriority = $tasks->groupBy(fn (Task $task) => $this->getTaskPriorityValue($task) ?? 'none')
            ->map(fn (Collection $groupedTasks) => $groupedTasks->count());

        $periodTasks = $tasks
            ->map(fn (Task $task) => $this->formatTaskForReport($task))
            ->sortByDesc(function (array $task) {
                return $task['activityDate'] ?? $task['updatedAt'] ?? $task['createdAt'] ?? '';
            })
            ->values();

        $tasksWithBounces = $periodTasks
            ->filter(fn (array $task) => ($task['reopenCount'] ?? 0) > 0)
            ->values();

        return [
            'total' => $tasks->count(),
            'completed' => $tasks->filter(fn (Task $task) => $this->getTaskStatusValue($task) === TaskStatus::COMPLETED->value)->count(),
            'inProgress' => $tasks->filter(fn (Task $task) => $this->getTaskStatusValue($task) === TaskStatus::IN_PROGRESS->value)->count(),
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'tasksWithBounces' => $tasksWithBounces,
            'recentTasks' => $periodTasks->take(15)->values(),
            'periodTasks' => $periodTasks,
            'totalBounces' => $tasks->sum('reopen_count'),
        ];
    }

    /**
     * Get project breakdown for Blade template.
     * Returns the same grouped project data shown in the API.
     */
    private function getProjectBreakdown(User $user, array $filters): array
    {
        return $this->getProjectsData($user, $filters)['projects'] ?? [];
    }

    /**
     * Get weekly statistics for Blade template.
     * Returns array with structure: ['weekNumber', 'dateRange', 'totalHours', 'workingDays', 'dailyBreakdown']
     */
    private function getWeeklyStatistics(User $user, array $filters): array
    {
        $dateFrom = !empty($filters['dateFrom']) ? Carbon::parse($filters['dateFrom']) : Carbon::now()->startOfMonth();
        $dateTo = !empty($filters['dateTo']) ? Carbon::parse($filters['dateTo']) : Carbon::now()->endOfMonth();

        // Get all time entries for the period
        $timeEntries = TimeEntry::where('user_id', $user->id)
            ->whereNotNull('end_time')
            ->whereBetween('start_time', [$dateFrom, $dateTo])
            ->get();

        // Get attendance data for status indicators
        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get()
            ->keyBy('date');

        // Group by week
        $weeklyData = [];
        $currentDate = $dateFrom->copy()->startOfWeek();

        while ($currentDate->lte($dateTo)) {
            $weekStart = $currentDate->copy();
            $weekEnd = $currentDate->copy()->endOfWeek();
            
            // Get time entries for this week
            $weekTimeEntries = $timeEntries->filter(function ($entry) use ($weekStart, $weekEnd) {
                $entryDate = Carbon::parse($entry->start_time);
                return $entryDate->between($weekStart, $weekEnd);
            });

            // Calculate daily breakdown
            $dailyBreakdown = [];
            $workingDays = 0;
            
            for ($day = $weekStart->copy(); $day->lte($weekEnd); $day->addDay()) {
                $dayEntries = $weekTimeEntries->filter(function ($entry) use ($day) {
                    return Carbon::parse($entry->start_time)->isSameDay($day);
                });

                $dayHours = round($dayEntries->sum('duration_minutes') / 60, 1);
                $attendance = $attendances->get($day->format('Y-m-d'));
                
                // Determine status
                $status = 'no_work';
                if ($dayHours > 0) {
                    $workingDays++;
                    if ($attendance && $attendance->late_minutes > 0) {
                        $status = 'late';
                    } elseif ($dayHours >= 8) {
                        $status = 'on_time';
                    } else {
                        $status = 'partial';
                    }
                } elseif ($day->isWeekend()) {
                    $status = 'rest_day';
                } elseif ($day->isFuture()) {
                    $status = 'future';
                } else {
                    $status = 'absent';
                }

                $dailyBreakdown[] = [
                    'date' => $day->format('Y-m-d'),
                    'hours' => $dayHours,
                    'status' => $status,
                ];
            }

            $totalWeekHours = round($weekTimeEntries->sum('duration_minutes') / 60, 1);
            
            if ($totalWeekHours > 0 || $weekStart->lte(Carbon::now())) {
                $weeklyData[] = [
                    'weekNumber' => $weekStart->weekOfYear,
                    'dateRange' => $weekStart->format('M j') . ' - ' . $weekEnd->format('M j, Y'),
                    'totalHours' => $totalWeekHours,
                    'workingDays' => $workingDays,
                    'dailyBreakdown' => $dailyBreakdown,
                ];
            }

            $currentDate->addWeek();
        }

        return $weeklyData;
    }

    /**
     * Get projects data.
     */
    private function getProjectsData(User $user, array $filters): array
    {
        $tasks = $this->getPrimaryTasksForPeriod($user, $filters);

        $projects = $tasks
            ->filter(fn (Task $task) => $task->project !== null)
            ->groupBy('project_id')
            ->map(function (Collection $projectTasks) {
                /** @var Task $firstTask */
                $firstTask = $projectTasks->first();
                $project = $firstTask->project;

                $completedTasks = $projectTasks->filter(fn (Task $task) => $this->getTaskStatusValue($task) === TaskStatus::COMPLETED->value);
                $onTimeDeliveries = $completedTasks->filter(fn (Task $task) => ($task->delay_days ?? 0) <= 0)->count();
                $lateDeliveries = $completedTasks->filter(fn (Task $task) => ($task->delay_days ?? 0) > 0)->count();
                $userMinutes = $projectTasks->sum(fn (Task $task) => $this->calculateTaskMinutesFromEntries($task));

                $deliverables = $completedTasks
                    ->sortByDesc('completed_at')
                    ->pluck('title')
                    ->filter()
                    ->take(3)
                    ->implode(', ');

                return [
                    'projectId' => $project->id,
                    'title' => $project->title,
                    'name' => $project->title,
                    'clientName' => $project->client?->name,
                    'status' => $this->getEnumBackedValue($project->status) ?? '',
                    'userTimeSpent' => round($userMinutes / 60, 2),
                    'totalHours' => round($userMinutes / 60, 2),
                    'totalTasks' => $projectTasks->count(),
                    'completedTasks' => $completedTasks->count(),
                    'inProgressTasks' => $projectTasks->filter(fn (Task $task) => $this->getTaskStatusValue($task) === TaskStatus::IN_PROGRESS->value)->count(),
                    'onTimeDeliveries' => $onTimeDeliveries,
                    'lateDeliveries' => $lateDeliveries,
                    'keyDeliverables' => $deliverables !== '' ? $deliverables : 'No completed tasks',
                ];
            })
            ->sortByDesc('userTimeSpent')
            ->values();

        return [
            'total' => $projects->count(),
            'active' => $projects->filter(fn (array $project) => $project['status'] === 'in_progress')->count(),
            'completed' => $projects->filter(fn (array $project) => $project['status'] === 'completed')->count(),
            'projects' => $projects,
        ];
    }

    /**
     * Get the primary tasks relevant to the selected reporting period.
     */
    private function getPrimaryTasksForPeriod(User $user, array $filters): Collection
    {
        $cacheKey = implode('|', [
            $user->id,
            $filters['dateFrom'] ?? '',
            $filters['dateTo'] ?? '',
        ]);

        if (isset($this->primaryTasksCache[$cacheKey])) {
            return $this->primaryTasksCache[$cacheKey];
        }

        $query = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('is_primary', true);
        })->with([
            'project:id,title,status,client_id',
            'project.client:id,name',
            'timeEntries' => function ($q) use ($user, $filters) {
                $q->where('user_id', $user->id)
                    ->whereNotNull('end_time');

                $this->applyDateRangeConstraint($q, 'start_time', $filters);
            },
        ]);

        $this->applyTaskPeriodFilter($query, $user, $filters);

        return $this->primaryTasksCache[$cacheKey] = $query
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Limit tasks to the selected reporting period using activity dates and delivery dates.
     */
    private function applyTaskPeriodFilter(Builder $query, User $user, array $filters): void
    {
        if (empty($filters['dateFrom']) && empty($filters['dateTo'])) {
            return;
        }

        $query->where(function (Builder $nestedQuery) use ($user, $filters) {
            $this->applyDateRangeConstraint($nestedQuery, 'created_at', $filters);

            $nestedQuery->orWhere(function (Builder $completedQuery) use ($filters) {
                $completedQuery->whereNotNull('completed_at');
                $this->applyDateRangeConstraint($completedQuery, 'completed_at', $filters);
            });

            $nestedQuery->orWhereHas('timeEntries', function (Builder $timeEntriesQuery) use ($user, $filters) {
                $timeEntriesQuery->where('user_id', $user->id)
                    ->whereNotNull('end_time');

                $this->applyDateRangeConstraint($timeEntriesQuery, 'start_time', $filters);
            });
        });
    }

    /**
     * Apply optional date-from/date-to filters to a specific timestamp column.
     */
    private function applyDateRangeConstraint(Builder|Relation $query, string $column, array $filters): void
    {
        if (!empty($filters['dateFrom'])) {
            $query->whereDate($column, '>=', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $query->whereDate($column, '<=', $filters['dateTo']);
        }
    }

    /**
     * Convert a task model into the reporting payload used by the API and PDF.
     */
    private function formatTaskForReport(Task $task): array
    {
        $timeEntries = $task->timeEntries ?? collect([]);
        $startTime = $timeEntries->min('start_time');
        $endTime = $timeEntries->max('end_time');
        $periodMinutes = $this->calculateTaskMinutesFromEntries($task);
        $deliveryStatus = $this->getTaskDeliveryStatus($task);
        $activityDate = $endTime ?? $startTime ?? $task->completed_at ?? $task->updated_at ?? $task->created_at;

        return [
            'taskId' => $task->id,
            'projectId' => $task->project_id,
            'title' => $task->title,
            'projectTitle' => $task->project?->title,
            'status' => $this->getTaskStatusValue($task) ?? '',
            'priority' => $this->getTaskPriorityValue($task) ?? '',
            'reopenCount' => $task->reopen_count,
            'estimatedHours' => $task->estimated_hours,
            'totalTimeSpent' => round($periodMinutes / 60, 2),
            'loggedHours' => round($periodMinutes / 60, 2),
            'overallTaskHours' => round(($task->total_time_spent ?? 0) / 60, 2),
            'dueDate' => $task->due_date?->format('Y-m-d'),
            'completedAt' => $task->completed_at?->format('Y-m-d\TH:i:s'),
            'delayDays' => $task->delay_days,
            'isOverdue' => $task->isOverdue(),
            'startedAt' => $startTime ? Carbon::parse($startTime)->format('Y-m-d H:i') : null,
            'endedAt' => $endTime ? Carbon::parse($endTime)->format('Y-m-d H:i') : null,
            'createdAt' => $task->created_at?->format('Y-m-d'),
            'updatedAt' => $task->updated_at?->format('Y-m-d'),
            'activityDate' => $activityDate ? Carbon::parse($activityDate)->format('Y-m-d\TH:i:s') : null,
            'deliveryStatus' => $deliveryStatus['status'],
            'deliveryStatusLabel' => $deliveryStatus['label'],
            'deliveredOnTime' => $deliveryStatus['deliveredOnTime'],
        ];
    }

    /**
     * Sum the currently loaded time entries for a task.
     */
    private function calculateTaskMinutesFromEntries(Task $task): int
    {
        return (int) ($task->timeEntries?->sum('duration_minutes') ?? 0);
    }

    /**
     * Build a normalized delivery status for tasks shown in the report.
     */
    private function getTaskDeliveryStatus(Task $task): array
    {
        if ($this->getTaskStatusValue($task) === TaskStatus::COMPLETED->value) {
            $delayDays = $task->delay_days ?? 0;

            return [
                'status' => $delayDays > 0 ? 'late' : 'on_time',
                'label' => $delayDays > 0 ? "Late by {$delayDays} days" : 'On Time',
                'deliveredOnTime' => $delayDays <= 0,
            ];
        }

        if ($task->isOverdue()) {
            return [
                'status' => 'overdue',
                'label' => 'Overdue',
                'deliveredOnTime' => false,
            ];
        }

        return [
            'status' => 'in_progress',
            'label' => 'In Progress',
            'deliveredOnTime' => null,
        ];
    }

    /**
     * Safely normalize a backed enum or raw scalar to its stored value.
     */
    private function getEnumBackedValue(mixed $value): string|int|null
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (is_string($value) || is_int($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Normalize task status regardless of whether the attribute is enum or plain string.
     */
    private function getTaskStatusValue(Task $task): ?string
    {
        $status = $this->getEnumBackedValue($task->status);

        return is_string($status) ? $status : null;
    }

    /**
     * Normalize task priority regardless of whether the attribute is enum or plain string.
     */
    private function getTaskPriorityValue(Task $task): ?string
    {
        $priority = $this->getEnumBackedValue($task->priority);

        return is_string($priority) ? $priority : null;
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
        $tasks = $this->getPrimaryTasksForPeriod($user, $filters);
        $totalTasks = $tasks->count();

        if ($totalTasks === 0) {
            return 0.0;
        }

        $completedTasks = $tasks->filter(fn (Task $task) => $this->getTaskStatusValue($task) === TaskStatus::COMPLETED->value)->count();

        return ($completedTasks / $totalTasks) * 100;
    }

    /**
     * Calculate average bounce rate for PRIMARY assignees only.
     * This ensures accurate quality metrics by only counting tasks the user is primarily responsible for.
     */
    private function calculateAverageBounceRate(User $user, array $filters): float
    {
        $tasks = $this->getPrimaryTasksForPeriod($user, $filters);

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
        [$pointsWindowFrom, $pointsWindowTo] = $this->getPointsWindow();

        $pointTransactionQuery = PointTransaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$pointsWindowFrom, $pointsWindowTo]);
        
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
            $perPage = max($totalCount, 1);
        } else {
            // For API: Apply pagination (default 50 per page)
            $page = (int) ($filters['page'] ?? 1);
            $perPage = max((int) ($filters['perPage'] ?? 50), 1);
            $pointTransactions = $pointTransactionQuery->with('createdBy')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // Consolidate all transactions into a unified format
        $allTransactions = collect();

        // Add PointTransactions (automatic system)
        foreach ($pointTransactions as $pt) {
            $transactionTypeLabel = $this->getPointTransactionTypeLabel($pt);

            $allTransactions->push([
                'transactionId' => 'pt_' . $pt->id,
                'points' => $pt->points,
                'type' => $transactionTypeLabel,
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

        $allPeriodTransactions = PointTransaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$pointsWindowFrom, $pointsWindowTo])
            ->get();
        
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
        $automaticCount = $allPeriodTransactions->filter(fn($t) => !$this->isManualPointTransaction($t))->count();
        $manualCount = $allPeriodTransactions->filter(fn($t) => $this->isManualPointTransaction($t))->count();
        
        $bySource = [
            'automatic' => [
                'count' => $automaticCount,
                'earned' => $allPeriodTransactions->filter(fn($t) => !$this->isManualPointTransaction($t))->where('points', '>', 0)->sum('points'),
                'deducted' => abs($allPeriodTransactions->filter(fn($t) => !$this->isManualPointTransaction($t))->where('points', '<', 0)->sum('points')),
            ],
            'manual' => [
                'count' => $manualCount,
                'earned' => $allPeriodTransactions->filter(fn($t) => $this->isManualPointTransaction($t))->where('points', '>', 0)->sum('points'),
                'deducted' => abs($allPeriodTransactions->filter(fn($t) => $this->isManualPointTransaction($t))->where('points', '<', 0)->sum('points')),
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
            'window' => [
                'dateFrom' => $pointsWindowFrom->format('Y-m-d'),
                'dateTo' => $pointsWindowTo->format('Y-m-d'),
            ],
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
        [$pointsWindowFrom, $pointsWindowTo] = $this->getPointsWindow();
        $pointTransactions = PointTransaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$pointsWindowFrom, $pointsWindowTo])
            ->get();

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
     * Get the rolling two-month window used for all points views in the report.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function getPointsWindow(): array
    {
        return [
            Carbon::now()->subMonths(2)->startOfDay(),
            Carbon::now()->endOfDay(),
        ];
    }

    /**
     * Safely resolve a point transaction type label across enum, string, and legacy values.
     */
    private function getPointTransactionTypeLabel(PointTransaction $transaction): string
    {
        $type = $transaction->type;

        if ($type instanceof PointTransactionType) {
            return $type->label();
        }

        if (is_string($type) || is_int($type)) {
            $enum = PointTransactionType::tryFrom((string) $type);

            if ($enum instanceof PointTransactionType) {
                return $enum->label();
            }
        }

        $rawType = $transaction->getRawOriginal('type');

        if (is_string($rawType) || is_int($rawType)) {
            $enum = PointTransactionType::tryFrom((string) $rawType);

            if ($enum instanceof PointTransactionType) {
                return $enum->label();
            }

            return (string) $rawType;
        }

        return 'Unknown';
    }

    /**
     * Detect manual point transactions without assuming the cast is always a valid enum.
     */
    private function isManualPointTransaction(PointTransaction $transaction): bool
    {
        return str_starts_with($this->getPointTransactionTypeLabel($transaction), 'Manual');
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
        $tasks = $this->getPrimaryTasksForPeriod($user, $filters);
        
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

        // Debug logging
        // \Log::info('Productivity Trends Debug - Time Entries', [
        //     'user_id' => $user->id,
        //     'time_entries_count' => $timeEntries->count(),
        //     'filters' => $filters
        // ]);

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

        // Debug logging
        // \Log::info('Productivity Trends Debug - Completed Tasks', [
        //     'user_id' => $user->id,
        //     'completed_tasks_count' => $completedTasks->count(),
        //     'filters' => $filters
        // ]);

        $weeklyTasks = $completedTasks->groupBy(function ($task) {
            return Carbon::parse($task->completed_at)->format('Y-W');
        })->map(function ($weekTasks, $week) {
            return [
                'week' => $week,
                'completed' => $weekTasks->count(),
                'onTime' => $weekTasks->filter(fn($task) => ($task->delay_days ?? 0) <= 0)->count(),
            ];
        })->values();

        $result = [
            'weeklyTimeTracking' => $weeklyData,
            'weeklyTaskCompletion' => $weeklyTasks,
        ];

        // Debug the final result
        // \Log::info('Productivity Trends Result', [
        //     'user_id' => $user->id,
        //     'weekly_time_tracking_count' => count($weeklyData),
        //     'weekly_task_completion_count' => count($weeklyTasks),
        //     'result' => $result
        // ]);

        return $result;
    }
}
