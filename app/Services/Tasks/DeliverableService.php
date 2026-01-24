<?php

namespace App\Services\Tasks;

use App\Enums\DeliverableStatus;
use App\Enums\PointTransactionType;
use App\Models\Task;
use App\Models\TaskDeliverable;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Points\PointsService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeliverableService
{
    public function __construct(
        private PointsService $pointsService
    ) {}

    /**
     * Create a new deliverable for a task.
     */
    public function createDeliverable(Task $task, array $data): TaskDeliverable
    {
        // Validate that only WEEKLY tasks can have deliverables
        if ($task->type !== \App\Enums\TaskType::WEEKLY) {
            throw new InvalidArgumentException('Only weekly tasks can have deliverables.');
        }

        return DB::transaction(function () use ($task, $data) {
            // Get the next order number
            $nextOrder = $task->deliverables()->max('order') + 1;

            return TaskDeliverable::create([
                'task_id' => $task->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'acceptance_criteria' => $data['acceptanceCriteria'] ?? null,
                'is_bonus' => $data['isBonus'] ?? false,
                'order' => $data['order'] ?? $nextOrder,
                'status' => DeliverableStatus::PENDING,
            ]);
        });
    }

    /**
     * Update an existing deliverable.
     */
    public function updateDeliverable(TaskDeliverable $deliverable, array $data): TaskDeliverable
    {
        // Only allow updates if not yet reviewed
        if ($deliverable->isCompleted()) {
            throw new InvalidArgumentException('Cannot update completed deliverables.');
        }

        return DB::transaction(function () use ($deliverable, $data) {
            $deliverable->update([
                'title' => $data['title'] ?? $deliverable->title,
                'description' => $data['description'] ?? $deliverable->description,
                'acceptance_criteria' => $data['acceptanceCriteria'] ?? $deliverable->acceptance_criteria,
                'is_bonus' => $data['isBonus'] ?? $deliverable->is_bonus,
                'order' => $data['order'] ?? $deliverable->order,
            ]);

            return $deliverable->fresh();
        });
    }

    /**
     * Submit a deliverable for review.
     */
    public function submitDeliverable(TaskDeliverable $deliverable, User $developer, array $data): TaskDeliverable
    {
        if (!$deliverable->canBeSubmitted()) {
            throw new InvalidArgumentException('Deliverable cannot be submitted in its current state.');
        }

        // Validate that the developer is assigned to the task
        if (!$deliverable->task->isDeveloper($developer)) {
            throw new InvalidArgumentException('Only assigned developers can submit deliverables.');
        }

        return DB::transaction(function () use ($deliverable, $data) {
            $deliverable->update([
                'status' => DeliverableStatus::SUBMITTED,
                'submission_notes' => $data['submissionNotes'] ?? null,
                'submitted_at' => now(),
            ]);

            return $deliverable->fresh();
        });
    }

    /**
     * Start reviewing a deliverable.
     */
    public function startReview(TaskDeliverable $deliverable, User $reviewer): TaskDeliverable
    {
        if (!$deliverable->canBeReviewed()) {
            throw new InvalidArgumentException('Deliverable cannot be reviewed in its current state.');
        }

        // Validate reviewer permissions
        $this->validateReviewerPermissions($deliverable->task, $reviewer);

        return DB::transaction(function () use ($deliverable, $reviewer) {
            $deliverable->update([
                'status' => DeliverableStatus::UNDER_REVIEW,
                'reviewed_by' => $reviewer->id,
            ]);

            return $deliverable->fresh();
        });
    }

    /**
     * Approve a deliverable and award points.
     */
    public function approveDeliverable(
        TaskDeliverable $deliverable, 
        User $reviewer, 
        array $data
    ): TaskDeliverable {
        if (!$deliverable->canBeReviewed()) {
            throw new InvalidArgumentException('Deliverable cannot be reviewed in its current state.');
        }

        $this->validateReviewerPermissions($deliverable->task, $reviewer);

        return DB::transaction(function () use ($deliverable, $reviewer, $data) {
            $pointsAwarded = $data['pointsAwarded'] ?? 1;
            $reviewNotes = $data['reviewNotes'] ?? null;

            // Update deliverable
            $deliverable->update([
                'status' => DeliverableStatus::APPROVED,
                'review_notes' => $reviewNotes,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'points_awarded' => $pointsAwarded,
            ]);

            // Award points to the task assignee(s)
            $this->awardPointsToAssignees($deliverable, $pointsAwarded, $reviewer);

            return $deliverable->fresh();
        });
    }

    /**
     * Reject a deliverable and deduct points.
     */
    public function rejectDeliverable(
        TaskDeliverable $deliverable, 
        User $reviewer, 
        array $data
    ): TaskDeliverable {
        if (!$deliverable->canBeReviewed()) {
            throw new InvalidArgumentException('Deliverable cannot be reviewed in its current state.');
        }

        $this->validateReviewerPermissions($deliverable->task, $reviewer);

        return DB::transaction(function () use ($deliverable, $reviewer, $data) {
            $pointsDeducted = $data['pointsDeducted'] ?? 1;
            $reviewNotes = $data['reviewNotes'] ?? 'Deliverable rejected';

            // Update deliverable
            $deliverable->update([
                'status' => DeliverableStatus::REJECTED,
                'review_notes' => $reviewNotes,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'points_awarded' => -$pointsDeducted,
            ]);

            // Deduct points from the task assignee(s)
            $this->deductPointsFromAssignees($deliverable, $pointsDeducted, $reviewer);

            return $deliverable->fresh();
        });
    }

    /**
     * Delete a deliverable (only if not submitted yet).
     */
    public function deleteDeliverable(TaskDeliverable $deliverable): bool
    {
        if ($deliverable->status !== DeliverableStatus::PENDING) {
            throw new InvalidArgumentException('Cannot delete submitted deliverables.');
        }

        return $deliverable->delete();
    }

    /**
     * Reorder deliverables for a task.
     */
    public function reorderDeliverables(Task $task, array $deliverableIds): void
    {
        DB::transaction(function () use ($task, $deliverableIds) {
            foreach ($deliverableIds as $index => $deliverableId) {
                $task->deliverables()
                    ->where('id', $deliverableId)
                    ->update(['order' => $index + 1]);
            }
        });
    }

    /**
     * Get deliverables summary for a task.
     */
    public function getDeliverablesSummary(Task $task): array
    {
        $deliverables = $task->deliverables;
        
        return [
            'total' => $deliverables->count(),
            'pending' => $deliverables->where('status', DeliverableStatus::PENDING)->count(),
            'submitted' => $deliverables->where('status', DeliverableStatus::SUBMITTED)->count(),
            'under_review' => $deliverables->where('status', DeliverableStatus::UNDER_REVIEW)->count(),
            'approved' => $deliverables->where('status', DeliverableStatus::APPROVED)->count(),
            'rejected' => $deliverables->where('status', DeliverableStatus::REJECTED)->count(),
            'total_points_awarded' => $deliverables->where('status', DeliverableStatus::APPROVED)->sum('points_awarded'),
            'total_points_deducted' => abs($deliverables->where('status', DeliverableStatus::REJECTED)->sum('points_awarded')),
        ];
    }

    /**
     * Validate that the user has permission to review deliverables for this task.
     */
    private function validateReviewerPermissions(Task $task, User $reviewer): void
    {
        $canReview = $reviewer->hasAnyRole(['admin', 'super-admin']) ||
                    $task->isReviewer($reviewer) ||
                    $reviewer->can('review_task_deliverables');

        if (!$canReview) {
            throw new InvalidArgumentException('User does not have permission to review this deliverable.');
        }
    }

    /**
     * Award points to task assignees.
     */
    private function awardPointsToAssignees(TaskDeliverable $deliverable, int $points, User $reviewer): void
    {
        $developers = $deliverable->task->developers;
        
        foreach ($developers as $assignee) {
            $this->pointsService->awardPoints(
                $assignee->user,
                $points,
                "Deliverable approved: {$deliverable->title}",
                PointTransactionType::DELIVERABLE_APPROVED,
                $reviewer
            );
        }
    }

    /**
     * Deduct points from task assignees.
     */
    private function deductPointsFromAssignees(TaskDeliverable $deliverable, int $points, User $reviewer): void
    {
        $developers = $deliverable->task->developers;
        
        foreach ($developers as $assignee) {
            $this->pointsService->deductPoints(
                $assignee->user,
                $points,
                "Deliverable rejected: {$deliverable->title}",
                PointTransactionType::DELIVERABLE_REJECTED,
                $reviewer
            );
        }
    }
}