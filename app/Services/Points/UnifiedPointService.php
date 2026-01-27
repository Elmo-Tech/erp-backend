<?php

namespace App\Services\Points;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Unified Point Service
 * 
 * This service consolidates both the PointTransaction system (automatic) 
 * and UserPoint system (manual) to provide a unified interface for point management.
 * 
 * It ensures both systems stay in sync and provides comprehensive point tracking.
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

            // Create UserPoint for backward compatibility
            $userPoint = UserPoint::create([
                'user_id' => $user->id,
                'points_change' => $points,
                'points_before' => $pointsBefore,
                'points_after' => $pointsAfter,
                'reason' => $reason,
                'type' => 'manual',
                'transaction_type' => $transactionType->value,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            // Notification removed - points are tracked silently
            return [
                'pointTransaction' => $pointTransaction,
                'userPoint' => $userPoint,
            ];
        });
    }

    /**
     * Get comprehensive user point history from both systems
     */
    public function getUserHistory(User $user, ?int $limit = null, array $filters = []): array
    {
        // Get from PointTransaction
        $ptQuery = PointTransaction::where('user_id', $user->id);
        if (!empty($filters['dateFrom'])) {
            $ptQuery->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $ptQuery->whereDate('created_at', '<=', $filters['dateTo']);
        }
        $pointTransactions = $ptQuery->with('createdBy')->get();

        // Get from UserPoint
        $upQuery = UserPoint::where('user_id', $user->id);
        if (!empty($filters['dateFrom'])) {
            $upQuery->whereDate('created_at', '>=', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $upQuery->whereDate('created_at', '<=', $filters['dateTo']);
        }
        $userPoints = $upQuery->with('createdBy')->get();

        // Consolidate all transactions
        $allTransactions = collect();

        // Add PointTransactions
        foreach ($pointTransactions as $pt) {
            $allTransactions->push([
                'id' => 'pt_' . $pt->id,
                'points' => $pt->points,
                'type' => $pt->type->label(),
                'reason' => $pt->reason ?? '',
                'createdAt' => $pt->created_at,
                'createdBy' => $pt->createdBy?->name ?? 'System',
                'balanceAfter' => $pt->balance_after,
                'source' => 'automatic',
                'sourceType' => $pt->source_type,
                'sourceId' => $pt->source_id,
            ]);
        }

        // Add UserPoints (avoid duplicates by checking if they have corresponding PointTransaction)
        foreach ($userPoints as $up) {
            // Skip if this is a manual entry that already has a corresponding PointTransaction
            $hasCorrespondingPT = $pointTransactions->where('created_at', $up->created_at)
                ->where('points', $up->points_change)
                ->where('reason', $up->reason)
                ->isNotEmpty();

            if (!$hasCorrespondingPT) {
                $typeLabel = 'Manual';
                if ($up->transaction_type) {
                    try {
                        // Check if it's already an enum instance or needs conversion
                        if ($up->transaction_type instanceof PointTransactionType) {
                            $typeLabel = $up->transaction_type->label();
                        } else {
                            $typeLabel = PointTransactionType::from($up->transaction_type)->label();
                        }
                    } catch (\ValueError $e) {
                        $typeLabel = ucfirst(str_replace('_', ' ', $up->transaction_type));
                    }
                }

                $allTransactions->push([
                    'id' => 'up_' . $up->id,
                    'points' => $up->points_change,
                    'type' => $typeLabel,
                    'reason' => $up->reason,
                    'createdAt' => $up->created_at,
                    'createdBy' => $up->createdBy?->name ?? 'System',
                    'balanceAfter' => $up->points_after,
                    'source' => $up->type,
                    'sourceType' => null,
                    'sourceId' => null,
                ]);
            }
        }

        // Sort by creation date
        $sortedTransactions = $allTransactions->sortByDesc('createdAt');

        // Apply limit if specified
        if ($limit) {
            $sortedTransactions = $sortedTransactions->take($limit);
        }

        return $sortedTransactions->values()->toArray();
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

        $bySource = collect($history)->groupBy('source')->map(function ($group) {
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
            'bySource' => $bySource,
        ];
    }

    /**
     * Recalculate user's total points from all transactions
     */
    public function recalculateUserPoints(User $user): int
    {
        // Get all unique transactions (avoid double counting)
        $history = $this->getUserHistory($user);
        $total = collect($history)->sum('points');
        
        $user->update(['total_points' => $total]);

        return $total;
    }

    /**
     * Create a unified transaction (both PointTransaction and UserPoint)
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

            $userId = auth()->id();

            // Create PointTransaction (primary system)
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

            // Create UserPoint for backward compatibility (only for automatic transactions)
            UserPoint::create([
                'user_id' => $user->id,
                'points_change' => $points,
                'points_before' => $user->total_points - $points,
                'points_after' => $user->total_points,
                'reason' => $reason ?? $type->label(),
                'type' => 'automatic',
                'transaction_type' => $type->value,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Notification removed - points are tracked silently
            return $pointTransaction;
        });
    }

    /**
     * Award task completion points
     */
    public function awardTaskCompletionPoints(User $user, Model $task, bool $isOnTime = false): array
    {
        $results = [];

        // Award basic completion points
        $results['completion'] = $this->awardPoints(
            $user,
            PointTransactionType::TASK_COMPLETED,
            null,
            $task,
            "Task completed: {$task->title}",
            true
        );

        // Award on-time bonus if applicable
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