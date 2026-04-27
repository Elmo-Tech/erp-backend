<?php

namespace App\Modules\Lead\Actions;

use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadMeeting;
use App\Modules\User\Models\User;

class ScheduleLeadMeetingAction
{
    public function execute(Lead $lead, array $data, User $user): LeadMeeting
    {
        return LeadMeeting::create([
            'lead_id' => $lead->id,
            'title' => $data['title'],
            'location' => $data['location'],
            'meeting_link' => $data['meetingLink'] ?? null,
            'scheduled_at' => $data['scheduledAt'],
            'duration_minutes' => $data['durationMinutes'] ?? 60,
            'attendees' => $data['attendees'] ?? [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function update(LeadMeeting $meeting, array $data, User $user): LeadMeeting
    {
        $meeting->update([
            'title' => $data['title'] ?? $meeting->title,
            'location' => $data['location'] ?? $meeting->location,
            'meeting_link' => $data['meetingLink'] ?? $meeting->meeting_link,
            'scheduled_at' => $data['scheduledAt'] ?? $meeting->scheduled_at,
            'duration_minutes' => $data['durationMinutes'] ?? $meeting->duration_minutes,
            'attendees' => $data['attendees'] ?? $meeting->attendees,
            'minutes' => $data['minutes'] ?? $meeting->minutes,
            'outcome' => $data['outcome'] ?? $meeting->outcome,
            'updated_by' => $user->id,
        ]);

        return $meeting->fresh();
    }
}
