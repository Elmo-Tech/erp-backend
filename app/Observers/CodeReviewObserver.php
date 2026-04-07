<?php

namespace App\Observers;

use App\Enums\CodeReviewStatus;
use App\Enums\PointTransactionType;
use App\Models\CodeReview;
use App\Services\Points\UnifiedPointService;

/**
 * Observer for CodeReview model to handle point awards and deductions.
 *
 * This observer:
 * - Awards points to reviewers when they approve code reviews
 * - Deducts points from developers when their code is rejected
 * - Tracks code review quality metrics for performance evaluation
 *
 * Point System:
 * - CODE_REVIEW_APPROVED: +1 point to reviewer
 * - CODE_REVIEW_REJECTED: -1 point to developer
 */
class CodeReviewObserver
{
    public function __construct(
        protected UnifiedPointService $pointService
    ) {}

    /**
     * Handle the CodeReview "updated" event.
     *
     * This method is called when a code review status changes.
     * It handles point awards/deductions based on the review outcome.
     */
    public function updated(CodeReview $codeReview): void
    {
        // Only process if status is being changed
        if (! $codeReview->isDirty('status')) {
            return;
        }

        $oldStatus = $codeReview->getOriginal('status');
        $newStatus = $codeReview->status;

        // Handle case where old status might be a string (from database)
        if (is_string($oldStatus)) {
            $oldStatus = CodeReviewStatus::tryFrom($oldStatus);
        }

        // Handle case where new status might be a string
        if (is_string($newStatus)) {
            $newStatus = CodeReviewStatus::tryFrom($newStatus);
        }

        // Award points when code review is approved
        if ($newStatus === CodeReviewStatus::APPROVED && $oldStatus !== CodeReviewStatus::APPROVED) {
            // Award points to reviewer for approving code
            if ($codeReview->reviewer) {
                $this->pointService->awardPoints(
                    $codeReview->reviewer,
                    PointTransactionType::CODE_REVIEW_APPROVED,
                    null,
                    $codeReview,
                    "Code review approved for task: {$codeReview->task->title}",
                    true
                );
            }
        }

        // Deduct points when code review is rejected
        if ($newStatus === CodeReviewStatus::REJECTED && $oldStatus !== CodeReviewStatus::REJECTED) {
            // Deduct points from developer for rejected code
            if ($codeReview->developer) {
                $this->pointService->deductPoints(
                    $codeReview->developer,
                    PointTransactionType::CODE_REVIEW_REJECTED,
                    null,
                    $codeReview,
                    "Code review rejected for task: {$codeReview->task->title}",
                    true
                );
            }
        }

        // Handle status change from rejected/needs_revision back to approved
        if ($newStatus === CodeReviewStatus::APPROVED && $oldStatus === CodeReviewStatus::REJECTED) {
            // Award points to reviewer for the approval
            if ($codeReview->reviewer) {
                $this->pointService->awardPoints(
                    $codeReview->reviewer,
                    PointTransactionType::CODE_REVIEW_APPROVED,
                    null,
                    $codeReview,
                    "Code review approved (after revision) for task: {$codeReview->task->title}",
                    true
                );
            }
        }
    }
}
