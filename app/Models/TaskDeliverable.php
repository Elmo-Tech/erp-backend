<?php

namespace App\Models;

use App\Enums\DeliverableStatus;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDeliverable extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

    protected $fillable = [
        'task_id',
        'title',
        'description',
        'acceptance_criteria',
        'status',
        'submission_notes',
        'review_notes',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'points_awarded',
        'is_bonus',
        'order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliverableStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'points_awarded' => 'integer',
            'is_bonus' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Get the task that owns this deliverable.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the reviewer who reviewed this deliverable.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if deliverable can be submitted.
     */
    public function canBeSubmitted(): bool
    {
        return $this->status === DeliverableStatus::PENDING;
    }

    /**
     * Check if deliverable can be reviewed.
     */
    public function canBeReviewed(): bool
    {
        return in_array($this->status, [
            DeliverableStatus::SUBMITTED,
            DeliverableStatus::UNDER_REVIEW
        ]);
    }

    /**
     * Check if deliverable is completed (approved or rejected).
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, [
            DeliverableStatus::APPROVED,
            DeliverableStatus::REJECTED
        ]);
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            DeliverableStatus::PENDING => 'gray',
            DeliverableStatus::SUBMITTED => 'blue',
            DeliverableStatus::UNDER_REVIEW => 'yellow',
            DeliverableStatus::APPROVED => 'green',
            DeliverableStatus::REJECTED => 'red',
        };
    }

    /**
     * Scope to get deliverables for a specific task.
     */
    public function scopeForTask($query, int $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Scope to get deliverables by status.
     */
    public function scopeByStatus($query, DeliverableStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get deliverables pending review.
     */
    public function scopePendingReview($query)
    {
        return $query->whereIn('status', [
            DeliverableStatus::SUBMITTED,
            DeliverableStatus::UNDER_REVIEW
        ]);
    }

    /**
     * Scope to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('created_at');
    }
}