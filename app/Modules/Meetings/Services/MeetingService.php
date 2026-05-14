<?php

namespace App\Modules\Meetings\Services;

use App\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MeetingService
{
    public function scheduleMeeting(Model $subject, array $data, User $user): Meeting
    {
        return Meeting::create([
            'model_id' => $subject->id,
            'model_type' => get_class($subject),
            'status' => MeetingStatus::SCHEDULED,
            'title' => $data['title'],
            'meeting_type' => $data['meetingType'] ?? null,
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'location_details' => $data['locationDetails'] ?? null,
            'is_completed' => false,
            'assigned_to' => $data['assignedTo'] ?? $user->id,
            'internal_attendees' => $data['internalAttendees'] ?? [],
            'external_attendees' => $data['externalAttendees'] ?? [],
            'scheduled_at' => $data['scheduledAt'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function markAsComplete(Meeting $meeting, string $minutes, User $user): Meeting
    {
        $meeting->update([
            'status' => MeetingStatus::COMPLETED,
            'is_completed' => true,
            'completed_at' => now(),
            'outcome_summary' => $minutes,
            'updated_by' => $user->id,
        ]);

        return $meeting->fresh();
    }

    public function cancelMeeting(Meeting $meeting, string $reason, User $user): Meeting
    {
        $meeting->update([
            'status' => MeetingStatus::CANCELLED,
            'outcome_summary' => "Cancelled: {$reason}",
            'updated_by' => $user->id,
        ]);

        return $meeting->fresh();
    }

    public function getTodaySchedule(User $user)
    {
        return $this->visibleToUserQuery($user)
            ->whereDate('scheduled_at', now()->toDateString())
            ->with(['model', 'assignedTo'])
            ->orderBy('scheduled_at')
            ->get();
    }

    public function getUpcomingMeetings(User $user, int $hours = 24)
    {
        return $this->visibleToUserQuery($user)
            ->upcoming($hours)
            ->with(['model'])
            ->orderBy('scheduled_at')
            ->get();
    }

    public function getMeetingsForSubject(Model $subject, array $filters = [])
    {
        $query = Meeting::where('model_id', $subject->id)
            ->where('model_type', get_class($subject))
            ->with(['assignedTo'])
            ->orderBy('scheduled_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function visibleToUserQuery(User $user): Builder
    {
        return Meeting::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id)
                    ->orWhereJsonContains('internal_attendees', $user->id)
                    ->orWhereJsonContains('internal_attendees', (string) $user->id);
            });
    }
}
