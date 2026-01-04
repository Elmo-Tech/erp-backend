<?php

namespace App\Http\Resources\V1\Leads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadMeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'meetingId' => $this->id,
            'leadId' => $this->lead_id,
            'title' => $this->title ?? '',
            'location' => $this->location?->value ?? '',
            'locationLabel' => $this->location?->label() ?? '',
            'meetingLink' => $this->meeting_link ?? '',
            'scheduledAt' => $this->scheduled_at?->toISOString() ?? '',
            'durationMinutes' => $this->duration_minutes ?? 60,
            'attendees' => $this->attendees ?? [],
            'minutes' => $this->minutes ?? '',
            'outcome' => $this->outcome ?? '',
            'googleCalendarEventId' => $this->google_calendar_event_id ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}
