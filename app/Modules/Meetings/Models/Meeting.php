<?php

namespace App\Modules\Meetings\Models;

use App\Enums\MeetingLocation;
use App\Enums\MeetingStatus;
use App\Modules\Settings\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
        'model_id',
        'model_type',
        'status',
        'title',
        'meeting_type',
        'description',
        'location',
        'location_details',
        'outcome_summary',
        'is_completed',
        'assigned_to',
        'internal_attendees',
        'external_attendees',
        'scheduled_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MeetingStatus::class,
            'location' => MeetingLocation::class,
            'is_completed' => 'boolean',
            'internal_attendees' => 'array',
            'external_attendees' => 'array',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isScheduled(): bool
    {
        return $this->status === MeetingStatus::SCHEDULED;
    }

    public function isCompleted(): bool
    {
        return $this->is_completed || $this->status === MeetingStatus::COMPLETED;
    }

    public function needsOutcome(): bool
    {
        return $this->isCompleted() && empty($this->outcome_summary);
    }

    public function markAsCompleted(?string $outcomeSummary = null): void
    {
        $this->update([
            'status' => MeetingStatus::COMPLETED,
            'is_completed' => true,
            'completed_at' => now(),
            'outcome_summary' => $outcomeSummary,
        ]);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', MeetingStatus::SCHEDULED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeNeedsOutcome($query)
    {
        return $query->where('is_completed', true)
            ->whereNull('outcome_summary');
    }

    public function scopeUpcoming($query, int $hours = 24)
    {
        return $query->where('status', MeetingStatus::SCHEDULED)
            ->whereBetween('scheduled_at', [now(), now()->addHours($hours)]);
    }
}
