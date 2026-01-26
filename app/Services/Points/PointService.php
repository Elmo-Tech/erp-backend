<?php

namespace App\Services\Points;

use App\Enums\PointTransactionType;
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
            // Update user's total points
            $user->increment('total_points', $points);
            $user->refresh();

            // Create transaction record
            $userId = auth()->id();

            $transaction = PointTransaction::create([
                'user_id' => $user->id,
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
