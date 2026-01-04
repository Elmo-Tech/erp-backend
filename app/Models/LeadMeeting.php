<?php

namespace App\Models;

use App\Enums\MeetingLocation;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadMeeting extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

    protected $fillable = [
        'lead_id',
        'title',
        'location',
        'meeting_link',
        'scheduled_at',
        'duration_minutes',
        'attendees',
        'minutes',
        'outcome',
        'google_calendar_event_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'location' => MeetingLocation::class,
            'scheduled_at' => 'datetime',
            'attendees' => 'array',
            'duration_minutes' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
