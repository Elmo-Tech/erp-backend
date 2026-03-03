<?php

namespace App\Services\Points;

use App\Enums\PointTransactionType;
use App\Models\PointPeriod;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PointService
{
    /**
     * Award points to a user
     */
    public function awardPoints(
        User $user,
        PointTransactionType $type,
        ?int $points = null,
        ?Model $source = null,
        ?string $reason = null,
        bool $notify = true
    ): PointTransaction {
        $points = $points ?? $type->defaultPoints();

        return $this->createTransaction($user, $points, $type, $source, $reason, $notify);
    }

    /**
     * Deduct points from a user
     */
    public function deductPoints(
        User $user,
        PointTransactionType $type,
        ?int $points = null,
        ?Model $source = null,
        ?string $reason = null,
        bool $notify = true
    ): PointTransaction {
        $points = $points ?? $type->defaultPoints();
        
        // Ensure points are negative for deductions
        $points = -abs($points);

        return $this->createTransaction($user, $points, $type, $source, $reason, $notify);
    }

    /**
     * Create a point transaction
     */
    protected function createTransaction(
        User $user,
        int $points,
        PointTransactionType $type,
        ?Model $source,
        ?string $reason,
        bool $notify = true
    ): PointTransaction {
        return DB::transaction(function () use ($user, $points, $type, $source, $reason, $notify) {
            // Get active period
            $activePeriod = PointPeriod::getActivePeriod();
            
            if (!$activePeriod) {
                throw new \Exception('No active point period found. Please create a period first.');
            }
            
            // Update user's total points
            $user->increment('total_points', $points);
            $user->refresh();

            // Create transaction record
            $userId = auth()->id();

            $transaction = PointTransaction::create([
                'user_id' => $user->id,
                'period_id' => $activePeriod->id,
                'points' => $points,
                'type' => $type,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->id,
                'reason' => $reason,
                'balance_after' => $user->total_points,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Notification removed - points are tracked silently
            return $transaction;
        });
    }

    /**
     * Get user's point history
     */
    public function getUserHistory(User $user, ?int $limit = null)
    {
        $query = PointTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get user's points summary for a period
     */
    public function getUserSummary(User $user, $startDate, $endDate): array
    {
        $transactions = PointTransaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $earned = $transactions->where('points', '>', 0)->sum('points');
        $deducted = abs($transactions->where('points', '<', 0)->sum('points'));

        $byType = $transactions->groupBy('type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('points'),
            ];
        });

        return [
            'totalEarned' => $earned,
            'totalDeducted' => $deducted,
            'netPoints' => $earned - $deducted,
            'transactionCount' => $transactions->count(),
            'byType' => $byType,
        ];
    }

    /**
     * Recalculate user's total points from transactions
     * Use this if points get out of sync
     */
    public function recalculateUserPoints(User $user): int
    {
        $total = PointTransaction::where('user_id', $user->id)->sum('points');
        
        $user->update(['total_points' => $total]);

        return $total;
    }
}

    /**
     * Get points for a specific period
     */
    public function getUserPointsForPeriod(User $user, PointPeriod $period): int
    {
        return $period->getUserPoints($user->id);
    }

    /**
     * Get leaderboard for current period
     */
    public function getCurrentPeriodLeaderboard(int $limit = 10): array
    {
        $activePeriod = PointPeriod::getActivePeriod();
        
        if (!$activePeriod) {
            return [];
        }
        
        return $activePeriod->getLeaderboard($limit);
    }

    /**
     * Get all periods with user's performance
     */
    public function getUserPeriodHistory(User $user): array
    {
        $periods = PointPeriod::closed()
            ->orderBy('end_date', 'desc')
            ->get();
        
        return $periods->map(function ($period) use ($user) {
            $points = $period->getUserPoints($user->id);
            $transactions = $period->transactions()
                ->where('user_id', $user->id)
                ->get();
            
            return [
                'periodId' => $period->id,
                'periodName' => $period->name,
                'startDate' => $period->start_date->format('Y-m-d'),
                'endDate' => $period->end_date->format('Y-m-d'),
                'closedAt' => $period->closed_at?->format('Y-m-d H:i:s'),
                'totalPoints' => $points,
                'transactionCount' => $transactions->count(),
                'pointsEarned' => $transactions->where('points', '>', 0)->sum('points'),
                'pointsDeducted' => abs($transactions->where('points', '<', 0)->sum('points')),
            ];
        })->toArray();
    }
