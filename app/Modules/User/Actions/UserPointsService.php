<?php

namespace App\Modules\User\Actions;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserPointsService
{
    /**
     * Add points to a user with reason tracking.
     * Supports both positive (add) and negative (deduct) values.
     */
    public function addPoints(User $user, int $points, string $reason, User $admin, ?PointTransactionType $type = null): PointTransaction
    {
        return DB::transaction(function () use ($user, $points, $reason, $admin, $type) {
            $pointsBefore = $user->total_points ?? 0;
            $pointsAfter = $pointsBefore + $points; // Allow negative total

            // Update user's total points
            $user->update(['total_points' => $pointsAfter]);

            // Create point transaction (single source)
            $transactionType = $type ?? ($points > 0 ? PointTransactionType::MANUAL_BONUS : PointTransactionType::MANUAL_DEDUCTION);

            return PointTransaction::create([
                'user_id' => $user->id,
                'points' => $points,
                'type' => $transactionType,
                'reason' => $reason,
                'balance_after' => $pointsAfter,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        });
    }

    /**
     * Deduct points from a user with reason tracking.
     */
    public function deductPoints(User $user, int $points, string $reason, User $admin, ?PointTransactionType $type = null): PointTransaction
    {
        return DB::transaction(function () use ($user, $points, $reason, $admin, $type) {
            $pointsBefore = $user->total_points ?? 0;
            $pointsAfter = $pointsBefore - $points; // Allow negative points

            // Update user's total points
            $user->update(['total_points' => $pointsAfter]);

            // Create point transaction (single source)
            $transactionType = $type ?? PointTransactionType::MANUAL_DEDUCTION;

            return PointTransaction::create([
                'user_id' => $user->id,
                'points' => -abs($points),
                'type' => $transactionType,
                'reason' => $reason,
                'balance_after' => $pointsAfter,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        });
    }

    /**
     * Set user points to a specific value with reason tracking.
     */
    public function setPoints(User $user, int $newPoints, string $reason, User $admin, ?PointTransactionType $type = null): PointTransaction
    {
        return DB::transaction(function () use ($user, $newPoints, $reason, $admin, $type) {
            $pointsBefore = $user->total_points;
            $pointsChange = $newPoints - $pointsBefore;

            // Update user's total points
            $user->update(['total_points' => $newPoints]);

            // Create point transaction (single source)
            $transactionType = $type ?? ($pointsChange >= 0 ? PointTransactionType::MANUAL_BONUS : PointTransactionType::MANUAL_DEDUCTION);

            return PointTransaction::create([
                'user_id' => $user->id,
                'points' => $pointsChange,
                'type' => $transactionType,
                'reason' => $reason,
                'balance_after' => $newPoints,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        });
    }

    /**
     * Get user points history with pagination.
     */
    public function getUserPointsHistory(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return PointTransaction::where('user_id', $user->id)
            ->with(['createdBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get all users with their current points.
     */
    public function getAllUsersWithPoints(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::employees()->select([
            'id',
            'name',
            'email',
            'avatar',
            'total_points',
            'is_active',
            'created_at',
        ]);

        // Apply filters
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($filters['isActive'])) {
            $query->where('is_active', $filters['isActive']);
        }

        // Sort by points descending by default
        $sortBy = $filters['sortBy'] ?? 'total_points';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get monthly points summary for a user.
     */
    public function getMonthlySummary(User $user, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $pointsHistory = PointTransaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalAdded = $pointsHistory->where('points', '>', 0)->sum('points');
        $totalDeducted = abs($pointsHistory->where('points', '<', 0)->sum('points'));
        $netChange = $totalAdded - $totalDeducted;

        return [
            'year' => $year,
            'month' => $month,
            'totalAdded' => $totalAdded,
            'totalDeducted' => $totalDeducted,
            'netChange' => $netChange,
            'transactionCount' => $pointsHistory->count(),
            'currentPoints' => $user->total_points,
            'pointsAtStartOfMonth' => $user->total_points - $netChange,
        ];
    }

    /**
     * Get points leaderboard.
     */
    public function getLeaderboard(int $limit = 10): Collection
    {
        return User::employees()
            ->select(['id', 'name', 'avatar', 'total_points'])
            ->where('is_active', true)
            ->orderBy('total_points', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent points activities across all users.
     */
    public function getRecentActivities(int $limit = 20): Collection
    {
        return PointTransaction::with(['user:id,name,avatar', 'createdBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Automatically add points for task completion (called from TaskService).
     */
    public function addTaskCompletionPoints(User $user, int $points, string $taskTitle): PointTransaction
    {
        return DB::transaction(function () use ($user, $points, $taskTitle) {
            $pointsBefore = $user->total_points ?? 0;
            $pointsAfter = $pointsBefore + $points;

            // Update user's total points
            $user->update(['total_points' => $pointsAfter]);

            // Create automatic point transaction
            $transactionType = $points >= 0 ? PointTransactionType::TASK_ON_TIME : PointTransactionType::TASK_LATE;

            return PointTransaction::create([
                'user_id' => $user->id,
                'points' => $points,
                'type' => $transactionType,
                'reason' => "Task completed: {$taskTitle}",
                'balance_after' => $pointsAfter,
                'created_by' => $user->id, // Self-generated
                'updated_by' => $user->id,
            ]);
        });
    }

    /**
     * Get points statistics for admin dashboard.
     */
    public function getPointsStatistics(): array
    {
        $totalUsers = User::employees()->where('is_active', true)->count();
        $totalPoints = User::employees()->where('is_active', true)->sum('total_points');
        $averagePoints = $totalUsers > 0 ? round($totalPoints / $totalUsers, 2) : 0;

        $thisMonth = Carbon::now()->startOfMonth();
        $monthlyActivity = PointTransaction::where('created_at', '>=', $thisMonth)->count();

        $topUser = User::employees()
            ->where('is_active', true)
            ->orderBy('total_points', 'desc')
            ->first();

        return [
            'totalUsers' => $totalUsers,
            'totalPoints' => $totalPoints,
            'averagePoints' => $averagePoints,
            'monthlyActivity' => $monthlyActivity,
            'topUser' => $topUser ? [
                'name' => $topUser->name,
                'points' => $topUser->total_points,
            ] : null,
        ];
    }

    /**
     * Get points activity report with date range and filters.
     */
    public function getPointsReport(array $filters = [], int $perPage = 20): array
    {
        $baseQuery = PointTransaction::query();

        // Apply date filters
        if (! empty($filters['dateFrom'])) {
            $baseQuery->whereDate('created_at', '>=', $filters['dateFrom']);
        }

        if (! empty($filters['dateTo'])) {
            $baseQuery->whereDate('created_at', '<=', $filters['dateTo']);
        }

        // Apply user filter
        if (! empty($filters['userId'])) {
            $baseQuery->where('user_id', $filters['userId']);
        }

        // Apply type filter
        if (! empty($filters['type'])) {
            $baseQuery->where('type', $filters['type']);
        }

        // Get paginated results
        $activities = (clone $baseQuery)->with(['user:id,name,email', 'createdBy:id,name'])->orderBy('created_at', 'desc')->paginate($perPage);

        // Get summary data
        $summaryData = (clone $baseQuery)->get();

        $totalPointsAdded = $summaryData->where('points', '>', 0)->sum('points');
        $totalPointsDeducted = abs($summaryData->where('points', '<', 0)->sum('points'));

        return [
            'activities' => $activities->items(),
            'summary' => [
                'totalActivities' => $summaryData->count(),
                'totalPointsAdded' => $totalPointsAdded,
                'totalPointsDeducted' => $totalPointsDeducted,
                'netChange' => $totalPointsAdded - $totalPointsDeducted,
                'period' => [
                    'from' => $filters['dateFrom'] ?? null,
                    'to' => $filters['dateTo'] ?? null,
                ],
            ],
            'pagination' => [
                'total' => $activities->total(),
                'count' => $activities->count(),
                'perPage' => $activities->perPage(),
                'currentPage' => $activities->currentPage(),
                'totalPages' => $activities->lastPage(),
            ],
        ];
    }
}
