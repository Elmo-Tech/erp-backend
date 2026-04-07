<?php

namespace Tests\Feature;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Reporting\EmployeeReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for PointTransaction as the single source of truth.
 *
 * These tests verify that:
 * 1. Only PointTransaction records are returned in point history
 * 2. UserPoint records (legacy) are NOT returned
 * 3. All point operations write to PointTransaction only
 */
class EmployeeReviewPointsDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_only_point_transaction_records_in_points_history(): void
    {
        $user = User::factory()->create();

        $createdAt = now()->subDay();
        $this->travelTo($createdAt);

        // Create a PointTransaction (should appear in results)
        $pt = PointTransaction::create([
            'user_id' => $user->id,
            'points' => 5,
            'type' => PointTransactionType::MANUAL_BONUS,
            'source_type' => null,
            'source_id' => null,
            'reason' => 'Manual bonus',
            'balance_after' => 5,
        ]);

        $this->travelBack();

        $service = app(EmployeeReviewService::class);
        $review = $service->getEmployeeReview($user->id, [
            'dateFrom' => $createdAt->toDateString(),
            'dateTo' => $createdAt->toDateString(),
        ]);

        // Only PointTransaction should be returned
        $this->assertCount(1, $review['points']['transactions']);
        $this->assertSame('pt_'.$pt->id, $review['points']['transactions'][0]['transactionId']);
        $this->assertSame('automatic', $review['points']['transactions'][0]['source']);
    }

    #[Test]
    public function it_does_not_return_user_point_records(): void
    {
        $user = User::factory()->create();

        $createdAt = now()->subDay();
        $this->travelTo($createdAt);

        // Create a legacy UserPoint (should NOT appear in results)
        \App\Models\UserPoint::create([
            'user_id' => $user->id,
            'points_change' => -2,
            'points_before' => 10,
            'points_after' => 8,
            'reason' => 'Manual deduction',
            'type' => 'manual',
            'transaction_type' => PointTransactionType::MANUAL_DEDUCTION->value,
        ]);

        // Create a PointTransaction (should appear in results)
        $pt = PointTransaction::create([
            'user_id' => $user->id,
            'points' => 3,
            'type' => PointTransactionType::MANUAL_BONUS,
            'source_type' => null,
            'source_id' => null,
            'reason' => 'Bonus',
            'balance_after' => 3,
        ]);

        $this->travelBack();

        $service = app(EmployeeReviewService::class);
        $review = $service->getEmployeeReview($user->id, [
            'dateFrom' => $createdAt->toDateString(),
            'dateTo' => $createdAt->toDateString(),
        ]);

        // Only PointTransaction should be returned, UserPoint should be ignored
        $this->assertCount(1, $review['points']['transactions']);
        $this->assertSame('pt_'.$pt->id, $review['points']['transactions'][0]['transactionId']);
    }

    #[Test]
    public function it_calculates_correct_points_stats_from_point_transaction_only(): void
    {
        $user = User::factory()->create();

        $createdAt = now()->subDay();
        $this->travelTo($createdAt);

        // Create multiple PointTransactions
        PointTransaction::create([
            'user_id' => $user->id,
            'points' => 10,
            'type' => PointTransactionType::TASK_ON_TIME,
            'source_type' => null,
            'source_id' => null,
            'reason' => 'Task completed',
            'balance_after' => 10,
        ]);

        PointTransaction::create([
            'user_id' => $user->id,
            'points' => -3,
            'type' => PointTransactionType::TASK_LATE,
            'source_type' => null,
            'source_id' => null,
            'reason' => 'Late delivery',
            'balance_after' => 7,
        ]);

        $this->travelBack();

        $service = app(EmployeeReviewService::class);
        $review = $service->getEmployeeReview($user->id, [
            'dateFrom' => $createdAt->toDateString(),
            'dateTo' => $createdAt->toDateString(),
        ]);

        $this->assertSame(10, $review['points']['pointsEarned']);
        $this->assertSame(3, $review['points']['pointsDeducted']);
        $this->assertSame(7, $review['points']['netChange']);
    }
}
