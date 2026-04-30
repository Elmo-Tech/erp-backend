<?php

namespace App\Modules\Lead\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadMeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'meetingId' => $this->id,
            'clientId' => $this->client_id,
            'title' => $this->title ?? '',
            'location' => $this->location?->value ?? '',
            'locationLabel' => $this->location?->label() ?? '',
            'status' => $this->status?->value ?? 'scheduled',
            'statusLabel' => $this->status?->label() ?? '',
            'statusColor' => $this->status?->color() ?? 'blue',
            'meetingLink' => $this->meeting_link ?? '',
            'scheduledAt' => $this->scheduled_at?->toISOString() ?? '',
            'endTime' => $this->end_time?->toISOString() ?? '',
            'durationMinutes' => $this->duration_minutes ?? 60,
            'attendees' => $this->attendees ?? [],
            'clientAttendees' => $this->client_attendees ?? [],
            'minutes' => $this->minutes ?? '',
            'outcome' => $this->outcome ?? '',
            'attachments' => $this->attachments ?? [],
            'cancellationReason' => $this->cancellation_reason ?? '',
            'reminderSent' => (bool) $this->reminder_sent,
            'outcomeReminderSent' => (bool) $this->outcome_reminder_sent,
            'googleCalendarEventId' => $this->google_calendar_event_id ?? '',
            'isScheduled' => $this->isScheduled(),
            'isCompleted' => $this->isCompleted(),
            'needsMinutes' => $this->needsMinutes(),
            'hasPassed' => $this->hasPassed(),
            'client' => $this->whenLoaded('client', function () {
                return [
                    'clientId' => $this->client->id,
                    'displayName' => $this->client->display_name ?? '',
                    'company' => $this->client->company_name ?? '',
                ];
            }),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}
