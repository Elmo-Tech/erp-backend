<?php

namespace App\Modules\ProjectHandover\Services;

use App\Enums\CodeReviewStatus;
use App\Modules\ProjectHandover\Models\CodeReview;
use App\Modules\Task\Models\Task;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;

class CodeReviewService
{
    /**
     * Create a new code review
     */
    public function createReview(Task $task, User $reviewer, User $developer, array $data): CodeReview
    {
        $review = CodeReview::create([
            'task_id' => $task->id,
            'reviewer_id' => $reviewer->id,
            'developer_id' => $developer->id,
            'code_quality_score' => $data['codeQualityScore'] ?? 0,
            'readability_score' => $data['readabilityScore'] ?? 0,
            'scalability_score' => $data['scalabilityScore'] ?? 0,
            'testing_score' => $data['testingScore'] ?? 0,
            'documentation_score' => $data['documentationScore'] ?? 0,
            'review_notes' => $data['reviewNotes'] ?? null,
            'improvement_suggestions' => $data['improvementSuggestions'] ?? null,
            'status' => CodeReviewStatus::PENDING,
        ]);

        $review->calculateAverageScore();
        $review->save();

        return $review;
    }

    /**
     * Update review scores
     */
    public function updateReview(CodeReview $review, array $data): CodeReview
    {
        $review->update([
            'code_quality_score' => $data['codeQualityScore'] ?? $review->code_quality_score,
            'readability_score' => $data['readabilityScore'] ?? $review->readability_score,
            'scalability_score' => $data['scalabilityScore'] ?? $review->scalability_score,
            'testing_score' => $data['testingScore'] ?? $review->testing_score,
            'documentation_score' => $data['documentationScore'] ?? $review->documentation_score,
            'review_notes' => $data['reviewNotes'] ?? $review->review_notes,
            'improvement_suggestions' => $data['improvementSuggestions'] ?? $review->improvement_suggestions,
        ]);

        $review->calculateAverageScore();
        $review->save();

        return $review;
    }

    /**
     * Approve a code review
     */
    public function approveReview(CodeReview $review): CodeReview
    {
        $review->update([
            'status' => CodeReviewStatus::APPROVED,
            'reviewed_at' => now(),
        ]);

        return $review;
    }

    /**
     * Request revision for a code review
     */
    public function requestRevision(CodeReview $review, string $suggestions): CodeReview
    {
        $review->update([
            'status' => CodeReviewStatus::NEEDS_REVISION,
            'improvement_suggestions' => $suggestions,
            'reviewed_at' => now(),
        ]);

        return $review;
    }

    /**
     * Reject a code review
     */
    public function rejectReview(CodeReview $review, string $reason): CodeReview
    {
        $review->update([
            'status' => CodeReviewStatus::REJECTED,
            'review_notes' => $reason,
            'reviewed_at' => now(),
        ]);

        return $review;
    }

    /**
     * Get developer's code review statistics
     */
    public function getDeveloperStats(User $developer, Carbon $startDate, Carbon $endDate): array
    {
        $reviews = CodeReview::where('developer_id', $developer->id)
            ->whereBetween('reviewed_at', [$startDate, $endDate])
            ->get();

        $approvedReviews = $reviews->where('status', CodeReviewStatus::APPROVED);

        return [
            'totalReviews' => $reviews->count(),
            'approved' => $approvedReviews->count(),
            'needsRevision' => $reviews->where('status', CodeReviewStatus::NEEDS_REVISION)->count(),
            'rejected' => $reviews->where('status', CodeReviewStatus::REJECTED)->count(),
            'averageScores' => [
                'codeQuality' => round($approvedReviews->avg('code_quality_score'), 2),
                'readability' => round($approvedReviews->avg('readability_score'), 2),
                'scalability' => round($approvedReviews->avg('scalability_score'), 2),
                'testing' => round($approvedReviews->avg('testing_score'), 2),
                'documentation' => round($approvedReviews->avg('documentation_score'), 2),
                'overall' => round($approvedReviews->avg('average_score'), 2),
            ],
            'approvalRate' => $reviews->count() > 0
                ? round(($approvedReviews->count() / $reviews->count()) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get pending reviews for a reviewer
     */
    public function getPendingReviewsForReviewer(User $reviewer): \Illuminate\Database\Eloquent\Collection
    {
        return CodeReview::where('reviewer_id', $reviewer->id)
            ->where('status', CodeReviewStatus::PENDING)
            ->with(['task', 'developer'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Calculate code quality score for performance evaluation
     * Returns score out of 15 (max code quality score in evaluation)
     */
    public function calculateCodeQualityScore(User $developer, Carbon $startDate, Carbon $endDate): float
    {
        $reviews = CodeReview::where('developer_id', $developer->id)
            ->whereBetween('reviewed_at', [$startDate, $endDate])
            ->where('status', CodeReviewStatus::APPROVED)
            ->get();

        if ($reviews->isEmpty()) {
            return 15; // Default full score if no reviews
        }

        $avgScore = $reviews->avg('average_score');

        // Convert 1-5 scale to 0-15 scale
        return round(($avgScore / 5) * 15, 2);
    }
}
