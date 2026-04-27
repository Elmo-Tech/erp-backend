<?php

namespace App\Models;

use App\Modules\User\Models\User;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $subject_id
 * @property string $subject_type
 * @property ActivityType $type
 * @property ActivityStatus $status
 * @property string|null $title
 * @property string|null $description
 * @property string|null $outcome_summary
 * @property bool $is_completed
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|null $subject
 * @property-read User|null $assignedTo
 */
class Activity extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'subject_id',
        'subject_type',
        'type',
        'status',
        'title',
        'description',
        'outcome_summary',
        'is_completed',
        'assigned_to',
        'scheduled_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'status' => ActivityStatus::class,
            'is_completed' => 'boolean',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the subject entity (Lead, Project, etc.)
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user assigned to this activity
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Check if activity is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->status === ActivityStatus::SCHEDULED;
    }

    /**
     * Check if activity is completed
     */
    public function isCompleted(): bool
    {
        return $this->is_completed || $this->status === ActivityStatus::COMPLETED;
    }

    /**
     * Check if activity needs outcome
     */
    public function needsOutcome(): bool
    {
        return $this->isCompleted() && empty($this->outcome_summary);
    }

    /**
     * Mark activity as completed
     */
    public function markAsCompleted(?string $outcomeSummary = null): void
    {
        $this->update([
            'status' => ActivityStatus::COMPLETED,
            'is_completed' => true,
            'completed_at' => now(),
            'outcome_summary' => $outcomeSummary,
        ]);
    }

    /**
     * Scope for scheduled activities
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', ActivityStatus::SCHEDULED);
    }

    /**
     * Scope for completed activities
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope for activities needing outcome
     */
    public function scopeNeedsOutcome($query)
    {
        return $query->where('is_completed', true)
            ->whereNull('outcome_summary');
    }

    /**
     * Scope for upcoming activities
     */
    public function scopeUpcoming($query, int $hours = 24)
    {
        return $query->where('status', ActivityStatus::SCHEDULED)
            ->whereBetween('scheduled_at', [now(), now()->addHours($hours)]);
    }

    /**
     * Scope for activities by type
     */
    public function scopeOfType($query, ActivityType $type)
    {
        return $query->where('type', $type);
    }
}
