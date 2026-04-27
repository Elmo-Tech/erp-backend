<?php

namespace App\Observers;

use App\Enums\DeliverableStatus;
use App\Modules\Task\Models\TaskActivity;
use App\Modules\Task\Models\TaskDeliverable;

/**
 * Observer for TaskDeliverable model to log activities and handle status changes.
 */
class TaskDeliverableObserver
{
    /**
     * Handle the TaskDeliverable "created" event.
     */
    public function created(TaskDeliverable $deliverable): void
    {
        // Only log activity if there's an authenticated user
        if (auth()->id()) {
            // Log deliverable creation activity
            TaskActivity::create([
                'task_id' => $deliverable->task_id,
                'user_id' => auth()->id(),
                'type' => 'deliverable_created',
                'action' => 'deliverable_created',
                'description' => "Created deliverable: {$deliverable->title}",
                'is_internal' => false,
                'metadata' => [
                    'deliverable_id' => $deliverable->id,
                    'deliverable_title' => $deliverable->title,
                    'is_bonus' => $deliverable->is_bonus,
                ],
            ]);
        }
    }

    /**
     * Handle the TaskDeliverable "updated" event.
     */
    public function updated(TaskDeliverable $deliverable): void
    {
        // Only log status changes if there's an authenticated user
        if (auth()->id() && $deliverable->isDirty('status')) {
            $oldStatus = $deliverable->getOriginal('status');
            $newStatus = $deliverable->status;

            // Convert string to enum if needed
            if (is_string($oldStatus)) {
                $oldStatus = DeliverableStatus::tryFrom($oldStatus);
            }

            $statusChangeMap = [
                DeliverableStatus::SUBMITTED->value => 'deliverable_submitted',
                DeliverableStatus::UNDER_REVIEW->value => 'deliverable_under_review',
                DeliverableStatus::APPROVED->value => 'deliverable_approved',
                DeliverableStatus::REJECTED->value => 'deliverable_rejected',
            ];

            $action = $statusChangeMap[$newStatus->value] ?? 'deliverable_status_changed';

            $description = match ($newStatus) {
                DeliverableStatus::SUBMITTED => "Submitted deliverable: {$deliverable->title}",
                DeliverableStatus::UNDER_REVIEW => "Started reviewing deliverable: {$deliverable->title}",
                DeliverableStatus::APPROVED => "Approved deliverable: {$deliverable->title}".
                    ($deliverable->points_awarded ? " (+{$deliverable->points_awarded} points)" : ''),
                DeliverableStatus::REJECTED => "Rejected deliverable: {$deliverable->title}".
                    ($deliverable->points_awarded < 0 ? " ({$deliverable->points_awarded} points)" : ''),
                default => "Changed deliverable status: {$deliverable->title}",
            };

            TaskActivity::create([
                'task_id' => $deliverable->task_id,
                'user_id' => auth()->id(),
                'type' => $action,
                'action' => $action,
                'description' => $description,
                'is_internal' => false,
                'metadata' => [
                    'deliverable_id' => $deliverable->id,
                    'deliverable_title' => $deliverable->title,
                    'old_status' => $oldStatus?->value,
                    'new_status' => $newStatus->value,
                    'points_awarded' => $deliverable->points_awarded,
                    'review_notes' => $deliverable->review_notes,
                ],
            ]);
        }
    }

    /**
     * Handle the TaskDeliverable "deleted" event.
     */
    public function deleted(TaskDeliverable $deliverable): void
    {
        // Only log activity if there's an authenticated user
        if (auth()->id()) {
            // Log deliverable deletion activity
            TaskActivity::create([
                'task_id' => $deliverable->task_id,
                'user_id' => auth()->id(),
                'type' => 'deliverable_deleted',
                'action' => 'deliverable_deleted',
                'description' => "Deleted deliverable: {$deliverable->title}",
                'is_internal' => false,
                'metadata' => [
                    'deliverable_id' => $deliverable->id,
                    'deliverable_title' => $deliverable->title,
                    'was_status' => $deliverable->status->value,
                ],
            ]);
        }
    }
}
