<?php

namespace App\Modules\Meetings\Models;

use App\Enums\MeetingLocation;
use App\Enums\MeetingStatus;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Meeting extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'subject_id',
        'subject_type',
        'status',
        'title',
        'description',
        'location',
        'location_details',
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
            'status' => MeetingStatus::class,
            'location' => MeetingLocation::class,
            'is_completed' => 'boolean',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Settings\Models\User::class, 'assigned_to');
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
