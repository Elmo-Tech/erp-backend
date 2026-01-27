<?php

namespace App\Services\Tasks;

use App\Enums\DeliverableStatus;
use App\Enums\PointTransactionType;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskDeliverable;
use App\Models\User;
use App\Services\Points\UnifiedPointService;
use App\Notifications\DeliverableSubmitted;
use App\Notifications\DeliverableReviewed;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeliverableService
{
    public function __construct(
        private UnifiedPointService $pointService
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

        return DB::transaction(function () use ($deliverable, $developer, $data) {
            $deliverable->update([
                'status' => DeliverableStatus::SUBMITTED,
                'submission_notes' => $data['submissionNotes'] ?? null,
                'submitted_at' => now(),
            ]);

            // Log activity
            TaskActivity::logDeliverableSubmitted(
                $deliverable->task,
                $developer,
                $deliverable->title
            );

            // Send notification to reviewers
            $this->notifyReviewers($deliverable, $developer);

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

            // Log activity in task history
            TaskActivity::logDeliverableApproved(
                $deliverable->task,
                $reviewer,
                $deliverable->title,
                $pointsAwarded,
                $deliverable->is_bonus
            );

            // Send notification to developers
            $this->notifyDevelopers($deliverable, $reviewer, 'approved');

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

            // Log activity in task history
            TaskActivity::logDeliverableRejected(
                $deliverable->task,
                $reviewer,
                $deliverable->title,
                $pointsDeducted,
                $reviewNotes
            );

            // Send notification to developers
            $this->notifyDevelopers($deliverable, $reviewer, 'rejected');

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
     * Award points to primary task assignees only.
     * Reviewers and team leads should not be affected by deliverable approvals.
     */
    private function awardPointsToAssignees(TaskDeliverable $deliverable, int $points, User $reviewer): void
    {
        // Only award points to primary assignees (the actual developers working on the task)
        $primaryAssignees = $deliverable->task->assignees()->where('is_primary', true)->get();
        
        foreach ($primaryAssignees as $assignee) {
            $this->pointService->awardPoints(
                $assignee->user,
                PointTransactionType::DELIVERABLE_APPROVED,
                $points,
                $deliverable,
                "Deliverable approved: {$deliverable->title}",
                true
            );
        }
    }

    /**
     * Deduct points from primary task assignees only.
     * Reviewers and team leads should not be affected by deliverable rejections.
     */
    private function deductPointsFromAssignees(TaskDeliverable $deliverable, int $points, User $reviewer): void
    {
        // Only deduct points from primary assignees (the actual developers working on the task)
        $primaryAssignees = $deliverable->task->assignees()->where('is_primary', true)->get();
        
        foreach ($primaryAssignees as $assignee) {
            $this->pointService->deductPoints(
                $assignee->user,
                PointTransactionType::DELIVERABLE_REJECTED,
                $points,
                $deliverable,
                "Deliverable rejected: {$deliverable->title}",
                true
            );
        }
    }

    /**
     * Send notifications to reviewers when deliverable is submitted.
     */
    private function notifyReviewers(TaskDeliverable $deliverable, User $developer): void
    {
        // Get all users who can review deliverables
        $reviewers = collect();
        
        // Add admins and super-admins (only if roles exist)
        $adminRoles = ['admin', 'super-admin'];
        foreach ($adminRoles as $roleName) {
            try {
                $roleUsers = User::role($roleName)->get();
                $reviewers = $reviewers->merge($roleUsers);
            } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
                // Role doesn't exist, skip it
                continue;
            }
        }
        
        // Add users with review permission (only if permission exists)
        try {
            $reviewers = $reviewers->merge(
                User::permission('review_task_deliverables')->get()
            );
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // Permission doesn't exist, skip it
        }
        
        // Add task reviewers if any
        if ($deliverable->task->reviewers) {
            $reviewers = $reviewers->merge($deliverable->task->reviewers);
        }
        
        // Remove duplicates and the developer who submitted
        $reviewers = $reviewers->unique('id')->reject(function ($user) use ($developer) {
            return $user->id === $developer->id;
        });
        
        // Send notifications
        foreach ($reviewers as $reviewer) {
            $reviewer->notify(new DeliverableSubmitted($deliverable, $developer->name));
        }
    }

    /**
     * Send notifications to developers when deliverable is reviewed.
     */
    private function notifyDevelopers(TaskDeliverable $deliverable, User $reviewer, string $status): void
    {
        $developers = $deliverable->task->developers;
        
        foreach ($developers as $assignee) {
            $assignee->user->notify(new DeliverableReviewed(
                $deliverable,
                $reviewer->name,
                $status,
                $reviewer->id,
                $deliverable->review_notes
            ));
        }
    }
}