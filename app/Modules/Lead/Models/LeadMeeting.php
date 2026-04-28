<?php

namespace App\Modules\Lead\Models;

use App\Enums\MeetingLocation;
use App\Enums\MeetingStatus;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeadMeeting extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'lead_id',
        'title',
        'location',
        'status',
        'meeting_link',
        'scheduled_at',
        'duration_minutes',
        'attendees',
        'client_attendees',
        'minutes',
        'outcome',
        'attachments',
        'cancellation_reason',
        'reminder_sent',
        'outcome_reminder_sent',
        'google_calendar_event_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'location' => MeetingLocation::class,
            'status' => MeetingStatus::class,
            'scheduled_at' => 'datetime',
            'attendees' => 'array',
            'client_attendees' => 'array',
            'attachments' => 'array',
            'duration_minutes' => 'integer',
            'reminder_sent' => 'boolean',
            'outcome_reminder_sent' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(\App\Modules\Activity\Models\ActivityReminder::class, 'remindable');
    }

    public function isScheduled(): bool
    {
        return $this->status === MeetingStatus::SCHEDULED;
    }

    public function isCompleted(): bool
    {
        return $this->status === MeetingStatus::COMPLETED;
    }

    public function needsMinutes(): bool
    {
        return $this->isCompleted() && empty($this->minutes);
    }

    public function getEndTimeAttribute(): ?\Carbon\Carbon
    {
        return $this->scheduled_at?->addMinutes($this->duration_minutes);
    }

    public function hasPassed(): bool
    {
        return $this->end_time && $this->end_time->isPast();
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', MeetingStatus::SCHEDULED);
    }

    public function scopeNeedsMinutes($query)
    {
        return $query->where('status', MeetingStatus::COMPLETED)
            ->whereNull('minutes');
    }

    public function scopeUpcoming($query, int $hours = 24)
    {
        return $query->where('status', MeetingStatus::SCHEDULED)
            ->whereBetween('scheduled_at', [now(), now()->addHours($hours)]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_at', today());
    }
}
