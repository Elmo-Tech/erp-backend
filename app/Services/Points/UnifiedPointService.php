<?php

namespace App\Services\Points;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Unified Point Service
 *
 * This service provides a unified interface for point management using PointTransaction
 * as the single source of truth.
 *
 * All point operations (automatic and manual) write to PointTransaction only.
 */
class UnifiedPointService
{
    /**
     * Award points to a user (creates entries in both systems for consistency)
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
     * Add manual points (admin action)
     */
    public function addManualPoints(
        User $user,
        int $points,
        string $reason,
        User $admin,
        ?PointTransactionType $type = null
    ): array {
        return DB::transaction(function () use ($user, $points, $reason, $admin, $type) {
            $pointsBefore = $user->total_points ?? 0;
            $pointsAfter = $pointsBefore + $points;

            // Update user's total points
            $user->update(['total_points' => $pointsAfter]);

            // Create PointTransaction for consistency
            $transactionType = $type ?? ($points > 0 ? PointTransactionType::MANUAL_BONUS : PointTransactionType::MANUAL_DEDUCTION);

            $pointTransaction = PointTransaction::create([
                'user_id' => $user->id,
                'points' => $points,
                'type' => $transactionType,
                'source_type' => null,
                'source_id' => null,
                'reason' => $reason,
                'balance_after' => $pointsAfter,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            // Notification removed - points are tracked silently
            return [
                'pointTransaction' => $pointTransaction,
            ];
        });
    }

    /**
     * Get comprehensive user point history from PointTransaction (single source of truth)
     */
    public function getUserHistory(User $user, ?int $limit = null, array $filters = []): array
    {
        // Get from PointTransaction only
        $query = PointTransaction::where('user_id', $user->id);

        if (! empty($filters['dateFrom'])) {
            $query->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (! empty($filters['dateTo'])) {
            $query->whereDate('created_at', '<=', $filters['dateTo']);
        }

        $pointTransactions = $query->with('createdBy')->orderBy('created_at', 'desc')->get();

        // Transform to unified format
        $allTransactions = $pointTransactions->map(function ($pt) {
            return [
                'id' => 'pt_'.$pt->id,
                'points' => $pt->points,
                'type' => $pt->type->label(),
                'reason' => $pt->reason ?? '',
                'createdAt' => $pt->created_at,
                'createdBy' => $pt->createdBy?->name ?? 'System',
                'balanceAfter' => $pt->balance_after,
                'source' => 'automatic',
                'sourceType' => $pt->source_type,
                'sourceId' => $pt->source_id,
            ];
        });

        // Apply limit if specified
        if ($limit) {
            $allTransactions = $allTransactions->take($limit);
        }

        return $allTransactions->values()->toArray();
    }

    /**
     * Get user's points summary for a period
     */
    public function getUserSummary(User $user, $startDate, $endDate): array
    {
        $history = $this->getUserHistory($user, null, [
            'dateFrom' => $startDate,
            'dateTo' => $endDate,
        ]);

        $earned = collect($history)->where('points', '>', 0)->sum('points');
        $deducted = abs(collect($history)->where('points', '<', 0)->sum('points'));

        $byType = collect($history)->groupBy('type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('points'),
            ];
        });

        return [
            'totalEarned' => $earned,
            'totalDeducted' => $deducted,
            'netPoints' => $earned - $deducted,
            'transactionCount' => count($history),
            'byType' => $byType,
        ];
    }

    /**
     * Recalculate user's total points from PointTransaction (single source of truth)
     */
    public function recalculateUserPoints(User $user): int
    {
        // Get all transactions from PointTransaction
        $total = PointTransaction::where('user_id', $user->id)->sum('points');

        $user->update(['total_points' => $total]);

        return $total;
    }

    /**
     * Create a unified transaction (PointTransaction only - single source of truth)
     */
    protected function createTransaction(
        User $user,
        int $points,
        PointTransactionType $type,
        ?Model $source,
        ?string $reason,
        bool $notify = true
    ): PointTransaction {
        return DB::transaction(function () use ($user, $points, $type, $source, $reason) {
            // Update user's total points
            $user->increment('total_points', $points);
            $user->refresh();

            $userId = auth()->id();

            // Create PointTransaction (single source of truth)
            $pointTransaction = PointTransaction::create([
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
            return $pointTransaction;
        });
    }

    /**
     * Award task completion points based on timing only
     * No base completion points - only timing-based points
     */
    public function awardTaskCompletionPoints(User $user, Model $task, bool $isOnTime = false, int $delayDays = 0): array
    {
        $results = [];

        // Only award points for on-time delivery
        if ($isOnTime) {
            $results['onTime'] = $this->awardPoints(
                $user,
                PointTransactionType::TASK_ON_TIME,
                null,
                $task,
                "Task delivered on time: {$task->title}",
                true
            );
        }
        // Deduct points for late delivery (1 point per day late)
        elseif ($delayDays > 0) {
            $results['late'] = $this->deductPoints(
                $user,
                PointTransactionType::TASK_LATE,
                $delayDays, // Deduct 1 point per day late
                $task,
                "Task delivered {$delayDays} day(s) late: {$task->title}",
                true
            );
        }

        return $results;
    }

    /**
     * Deduct points for task reopening (bug bounce)
     */
    public function deductTaskReopenPoints(User $user, Model $task): PointTransaction
    {
        return $this->deductPoints(
            $user,
            PointTransactionType::TASK_REOPENED,
            null,
            $task,
            "Task reopened from testing: {$task->title}",
            true
        );
    }

    /**
     * Award points for test scenario verification (passed)
     */
    public function awardTestScenarioPoints(User $user, Model $scenario): PointTransaction
    {
        return $this->awardPoints(
            $user,
            PointTransactionType::TEST_SCENARIO_VERIFIED,
            null,
            $scenario,
            "Test scenario verified as passed: {$scenario->title}",
            true
        );
    }

    /**
     * Deduct points for test scenario verification (failed)
     */
    public function deductTestScenarioPoints(User $user, Model $scenario): PointTransaction
    {
        return $this->deductPoints(
            $user,
            PointTransactionType::TEST_SCENARIO_FAILED,
            null,
            $scenario,
            "Test scenario verified as failed: {$scenario->title}",
            true
        );
    }
}
