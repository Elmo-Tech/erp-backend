<?php

namespace App\Services\Reporting;

use App\Enums\TaskStatus;
use App\Models\Attendance;
use App\Modules\Task\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class UserPerformanceService
{
    /**
     * Default work start time for late calculation (9:00 AM).
     */
    private const DEFAULT_WORK_START_TIME = '09:00:00';

    /**
     * Default excessive idle time threshold in minutes (2 hours).
     */
    private const DEFAULT_EXCESSIVE_IDLE_THRESHOLD = 120;

    /**
     * Get performance metrics for a user.
     *
     * @param  array  $filters  ['dateFrom' => string, 'dateTo' => string]
     * @return array{
     *     userId: int,
     *     name: string,
     *     totalTrackedMinutes: int,
     *     totalIdleMinutes: int,
     *     taskCompletionRate: float,
     *     averageBounceRate: float,
     *     attendanceDays: int
     * }
     */
    public function getUserPerformance(User $user, array $filters = []): array
    {
        // Validate date range
        $this->validateDateRange($filters);

        // Calculate total tracked minutes
        $totalTrackedMinutes = $this->calculateUserTrackedTime($user, $filters);

        // Calculate total idle minutes
        $totalIdleMinutes = $this->calculateUserIdleTime($user, $filters);

        // Calculate task completion rate
        $taskCompletionRate = $this->calculateTaskCompletionRate($user, $filters);

        // Calculate average bounce rate
        $averageBounceRate = $this->calculateUserBounceRate($user, $filters);

        // Count attendance days
        $attendanceDays = $this->countAttendanceDays($user, $filters);

        return [
            'userId' => $user->id,
            'name' => $user->name,
            'totalTrackedMinutes' => $totalTrackedMinutes,
            'totalIdleMinutes' => $totalIdleMinutes,
            'taskCompletionRate' => number_format($taskCompletionRate, 2, '.', ''),
            'averageBounceRate' => number_format($averageBounceRate, 2, '.', ''),
            'attendanceDays' => $attendanceDays,
        ];
    }

    /**
     * Get performance metrics for all users.
     *
     * @param  array  $filters  ['dateFrom' => string, 'dateTo' => string]
     */
    public function getAllUserPerformance(array $filters = []): Collection
    {
        // Validate date range
        $this->validateDateRange($filters);

        $users = User::all();
        $performanceData = collect();

        foreach ($users as $user) {
            $performanceData->push($this->getUserPerformance($user, $filters));
        }

        return $performanceData;
    }

    /**
     * Get attendance analytics.
     *
     * @param  array  $filters  ['dateFrom' => string, 'dateTo' => string]
     * @return array{
     *     totalLateMinutes: int,
     *     averageIdleTime: float,
     *     usersWithExcessiveIdle: Collection
     * }
     */
    public function getAttendanceAnalytics(array $filters = []): array
    {
        // Validate date range
        $this->validateDateRange($filters);

        // Build attendance query with filters
        $attendanceQuery = Attendance::with('user');

        if (! empty($filters['dateFrom'])) {
            $attendanceQuery->where('date', '>=', $filters['dateFrom']);
        }
        if (! empty($filters['dateTo'])) {
            $attendanceQuery->where('date', '<=', $filters['dateTo']);
        }

        $attendances = $attendanceQuery->get();

        // Calculate total late minutes
        $totalLateMinutes = $attendances->sum('late_minutes');

        // Calculate average idle time
        $totalIdleMinutes = $attendances->sum('idle_minutes');
        $averageIdleTime = $attendances->count() > 0 ? $totalIdleMinutes / $attendances->count() : 0;

        // Find users with excessive idle time
        $usersWithExcessiveIdle = $this->getUsersWithExcessiveIdle($filters);

        return [
            'totalLateMinutes' => $totalLateMinutes,
            'averageIdleTime' => number_format($averageIdleTime, 2, '.', ''),
            'usersWithExcessiveIdle' => $usersWithExcessiveIdle,
        ];
    }

    /**
     * Calculate total tracked time for a user in minutes.
     */
    private function calculateUserTrackedTime(User $user, array $filters): int
    {
        $query = TimeEntry::where('user_id', $user->id)
            ->whereNotNull('end_time'); // Only completed time entries

        // Apply date range filter
        if (! empty($filters['dateFrom'])) {
            $query->whereDate('start_time', '>=', $filters['dateFrom']);
        }
        if (! empty($filters['dateTo'])) {
            $query->whereDate('start_time', '<=', $filters['dateTo']);
        }

        return (int) $query->sum('duration_minutes');
    }

    /**
     * Calculate total idle time for a user in minutes.
     */
    private function calculateUserIdleTime(User $user, array $filters): int
    {
        $query = Attendance::where('user_id', $user->id);

        // Apply date range filter
        if (! empty($filters['dateFrom'])) {
            $query->where('date', '>=', $filters['dateFrom']);
        }
        if (! empty($filters['dateTo'])) {
            $query->where('date', '<=', $filters['dateTo']);
        }

        return (int) $query->sum('idle_minutes');
    }

    /**
     * Calculate task completion rate for a user (PRIMARY assignees only).
     * This ensures accurate performance metrics by only counting tasks the user is primarily responsible for.
     */
    private function calculateTaskCompletionRate(User $user, array $filters): float
    {
        $taskQuery = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('is_primary', true); // Only primary assignees
        });

        // Apply date range filter on task creation
        if (! empty($filters['dateFrom'])) {
            $taskQuery->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (! empty($filters['dateTo'])) {
            $taskQuery->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $totalTasks = $taskQuery->count();

        if ($totalTasks === 0) {
            return 0.0;
        }

        $completedTasks = (clone $taskQuery)->where('status', TaskStatus::COMPLETED)->count();

        return ($completedTasks / $totalTasks) * 100;
    }

    /**
     * Calculate average bounce rate for a user (PRIMARY assignees only).
     * This ensures accurate quality metrics by only counting tasks the user is primarily responsible for.
     */
    private function calculateUserBounceRate(User $user, array $filters): float
    {
        $taskQuery = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('is_primary', true); // Only primary assignees
        });

        // Apply date range filter on task creation
        if (! empty($filters['dateFrom'])) {
            $taskQuery->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (! empty($filters['dateTo'])) {
            $taskQuery->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $tasks = $taskQuery->get();

        if ($tasks->count() === 0) {
            return 0.0;
        }

        $totalBounces = $tasks->sum('reopen_count');

        return $totalBounces / $tasks->count();
    }

    /**
     * Count attendance days for a user.
     */
    private function countAttendanceDays(User $user, array $filters): int
    {
        $query = Attendance::where('user_id', $user->id);

        // Apply date range filter
        if (! empty($filters['dateFrom'])) {
            $query->where('date', '>=', $filters['dateFrom']);
        }
        if (! empty($filters['dateTo'])) {
            $query->where('date', '<=', $filters['dateTo']);
        }

        return $query->count();
    }

    /**
     * Get users with excessive idle time.
     *
     * @param  int  $threshold  Idle time threshold in minutes
     */
    private function getUsersWithExcessiveIdle(array $filters, int $threshold = self::DEFAULT_EXCESSIVE_IDLE_THRESHOLD): Collection
    {
        $attendanceQuery = Attendance::with('user')
            ->where('idle_minutes', '>', $threshold);

        // Apply date range filter
        if (! empty($filters['dateFrom'])) {
            $attendanceQuery->where('date', '>=', $filters['dateFrom']);
        }
        if (! empty($filters['dateTo'])) {
            $attendanceQuery->where('date', '<=', $filters['dateTo']);
        }

        return $attendanceQuery->get()
            ->groupBy('user_id')
            ->map(function ($attendances, $userId) {
                $user = $attendances->first()->user;
                $totalExcessiveIdle = $attendances->sum('idle_minutes');
                $daysWithExcessiveIdle = $attendances->count();

                return [
                    'userId' => $userId,
                    'userName' => $user->name,
                    'totalExcessiveIdleMinutes' => $totalExcessiveIdle,
                    'daysWithExcessiveIdle' => $daysWithExcessiveIdle,
                    'averageExcessiveIdlePerDay' => number_format($totalExcessiveIdle / $daysWithExcessiveIdle, 2, '.', ''),
                ];
            })
            ->values();
    }

    /**
     * Validate date range filters.
     *
     * @throws InvalidArgumentException
     */
    private function validateDateRange(array $filters): void
    {
        if (! empty($filters['dateFrom']) && ! empty($filters['dateTo'])) {
            $dateFrom = \Carbon\Carbon::parse($filters['dateFrom']);
            $dateTo = \Carbon\Carbon::parse($filters['dateTo']);

            if ($dateFrom->gt($dateTo)) {
                throw new InvalidArgumentException('dateFrom cannot be greater than dateTo');
            }
        }
    }
}
