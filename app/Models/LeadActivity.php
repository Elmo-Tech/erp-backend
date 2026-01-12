<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeadActivity extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'status',
        'title',
        'description',
        'outcome',
        'cancellation_reason',
        'next_action',
        'reminder_at',
        'reminder_sent',
        'outcome_reminder_sent',
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
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_at' => 'datetime',
            'reminder_sent' => 'boolean',
            'outcome_reminder_sent' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get reminders for this activity
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(ActivityReminder::class, 'remindable');
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
        return $this->status === ActivityStatus::COMPLETED;
    }

    /**
     * Check if activity needs outcome
     */
    public function needsOutcome(): bool
    {
        return $this->isCompleted() && empty($this->outcome);
    }

    /**
     * Scope for scheduled activities
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', ActivityStatus::SCHEDULED);
    }

    /**
     * Scope for activities needing outcome
     */
    public function scopeNeedsOutcome($query)
    {
        return $query->where('status', ActivityStatus::COMPLETED)
            ->whereNull('outcome');
    }

    /**
     * Scope for upcoming activities
     */
    public function scopeUpcoming($query, int $hours = 24)
    {
        return $query->where('status', ActivityStatus::SCHEDULED)
            ->whereBetween('scheduled_at', [now(), now()->addHours($hours)]);
    }
}
