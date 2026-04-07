<?php

namespace App\Models;

use App\Enums\DeliverableStatus;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDeliverable extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

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
     * Get the task that owns this deliverable
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who reviewed this deliverable
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope to get deliverables by status
     */
    public function scopeByStatus($query, DeliverableStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get bonus deliverables
     */
    public function scopeBonus($query)
    {
        return $query->where('is_bonus', true);
    }

    /**
     * Scope to get regular deliverables
     */
    public function scopeRegular($query)
    {
        return $query->where('is_bonus', false);
    }

    /**
     * Check if deliverable can be submitted
     */
    public function canBeSubmitted(): bool
    {
        return $this->status === DeliverableStatus::PENDING;
    }

    /**
     * Check if deliverable can be reviewed
     */
    public function canBeReviewed(): bool
    {
        return in_array($this->status, [
            DeliverableStatus::SUBMITTED,
            DeliverableStatus::UNDER_REVIEW,
        ]);
    }

    /**
     * Submit the deliverable
     */
    public function submit(?string $submissionNotes = null): void
    {
        if (! $this->canBeSubmitted()) {
            throw new \InvalidArgumentException('Deliverable cannot be submitted in current status');
        }

        $this->update([
            'status' => DeliverableStatus::SUBMITTED,
            'submission_notes' => $submissionNotes,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Review the deliverable
     */
    public function review(
        DeliverableStatus $status,
        int $reviewedBy,
        ?string $reviewNotes = null,
        int $pointsAwarded = 0
    ): void {
        if (! $this->canBeReviewed()) {
            throw new \InvalidArgumentException('Deliverable cannot be reviewed in current status');
        }

        if (! in_array($status, [DeliverableStatus::APPROVED, DeliverableStatus::REJECTED])) {
            throw new \InvalidArgumentException('Invalid review status');
        }

        $this->update([
            'status' => $status,
            'review_notes' => $reviewNotes,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
            'points_awarded' => $pointsAwarded,
        ]);
    }
}
