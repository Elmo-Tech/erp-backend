<?php

namespace App\Modules\Lead\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Traits\HasCreatedUpdatedBy;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeadActivity extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

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

    public function reminders(): MorphMany
    {
        return $this->morphMany(\App\Models\ActivityReminder::class, 'remindable');
    }

    public function isScheduled(): bool
    {
        return $this->status === ActivityStatus::SCHEDULED;
    }

    public function isCompleted(): bool
    {
        return $this->status === ActivityStatus::COMPLETED;
    }

    public function needsOutcome(): bool
    {
        return $this->isCompleted() && empty($this->outcome);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', ActivityStatus::SCHEDULED);
    }

    public function scopeNeedsOutcome($query)
    {
        return $query->where('status', ActivityStatus::COMPLETED)
            ->whereNull('outcome');
    }

    public function scopeUpcoming($query, int $hours = 24)
    {
        return $query->where('status', ActivityStatus::SCHEDULED)
            ->whereBetween('scheduled_at', [now(), now()->addHours($hours)]);
    }
}
