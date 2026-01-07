<?php

namespace App\Services\HR;

use App\Models\User;
use App\Models\UserPoint;
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
    public function addPoints(User $user, int $points, string $reason, User $admin): UserPoint
    {
        return DB::transaction(function () use ($user, $points, $reason, $admin) {
            $pointsBefore = $user->total_points;
            $pointsAfter = max(0, $pointsBefore + $points); // Don't allow negative total

            // Update user's total points
            $user->update(['total_points' => $pointsAfter]);

            // Create point record
            return UserPoint::create([
                'user_id' => $user->id,
                'points_change' => $points,
                'points_before' => $pointsBefore,
                'points_after' => $pointsAfter,
                'reason' => $reason,
                'type' => 'manual',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        });
    }

    /**
     * Deduct points from a user with reason tracking.
     */
    public function deductPoints(User $user, int $points, string $reason, User $admin): UserPoint
    {
        return DB::transaction(function () use ($user, $points, $reason, $admin) {
            $pointsBefore = $user->total_points;
            $pointsAfter = max(0, $pointsBefore - $points); // Don't allow negative points

            // Update user's total points
            $user->update(['total_points' => $pointsAfter]);

            // Create point record (negative change)
            return UserPoint::create([
                'user_id' => $user->id,
                'points_change' => -$points,
                'points_before' => $pointsBefore,
                'points_after' => $pointsAfter,
                'reason' => $reason,
                'type' => 'manual',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        });
    }

    /**
     * Set user points to a specific value with reason tracking.
     */
    public function setPoints(User $user, int $newPoints, string $reason, User $admin): UserPoint
    {
        return DB::transaction(function () use ($user, $newPoints, $reason, $admin) {
            $pointsBefore = $user->total_points;
            $pointsChange = $newPoints - $pointsBefore;

            // Update user's total points
            $user->update(['total_points' => $newPoints]);

            // Create point record
            return UserPoint::create([
                'user_id' => $user->id,
                'points_change' => $pointsChange,
                'points_before' => $pointsBefore,
                'points_after' => $newPoints,
                'reason' => $reason,
                'type' => 'manual',
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
        return UserPoint::where('user_id', $user->id)
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
            'created_at'
        ]);

        // Apply filters
        if (!empty($filters['search'])) {
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

        $pointsHistory = UserPoint::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalAdded = $pointsHistory->where('points_change', '>', 0)->sum('points_change');
        $totalDeducted = abs($pointsHistory->where('points_change', '<', 0)->sum('points_change'));
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
        return UserPoint::with(['user:id,name,avatar', 'createdBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Automatically add points for task completion (called from TaskService).
     */
    public function addTaskCompletionPoints(User $user, int $points, string $taskTitle): UserPoint
    {
        return DB::transaction(function () use ($user, $points, $taskTitle) {
            $pointsBefore = $user->total_points;
            $pointsAfter = $pointsBefore + $points;

            // Update user's total points
            $user->update(['total_points' => $pointsAfter]);

            // Create automatic point record
            return UserPoint::create([
                'user_id' => $user->id,
                'points_change' => $points,
                'points_before' => $pointsBefore,
                'points_after' => $pointsAfter,
                'reason' => "Task completed: {$taskTitle}",
                'type' => 'automatic',
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
        $monthlyActivity = UserPoint::where('created_at', '>=', $thisMonth)->count();

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
}