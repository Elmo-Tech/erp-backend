<?php

namespace App\Modules\Task\Models;

use App\Enums\DeliverableStatus;
use App\Modules\User\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Database\Factories\TaskDeliverableFactory;
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

    protected static function newFactory(): TaskDeliverableFactory
    {
        return TaskDeliverableFactory::new();
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [DeliverableStatus::APPROVED, DeliverableStatus::REJECTED]);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeByStatus($query, DeliverableStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeBonus($query)
    {
        return $query->where('is_bonus', true);
    }

    public function scopeRegular($query)
    {
        return $query->where('is_bonus', false);
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === DeliverableStatus::PENDING;
    }

    public function canBeReviewed(): bool
    {
        return in_array($this->status, [
            DeliverableStatus::SUBMITTED,
            DeliverableStatus::UNDER_REVIEW,
        ]);
    }

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
