<?php

namespace App\Services\Sales;

use App\Enums\ActivityStatus;
use App\Enums\MeetingStatus;
use App\Enums\ReminderType;
use App\Models\ActivityReminder;
use App\Models\LeadActivity;
use App\Models\LeadMeeting;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityService
{
    /**
     * Get paginated activities with filters
     */
    public function getActivities(array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(LeadActivity::class)
            ->with(['lead', 'user'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('lead_id'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::scope('scheduled'),
                AllowedFilter::scope('needs_outcome'),
                AllowedFilter::scope('upcoming'),
                'search' => AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('title', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['scheduled_at', 'created_at', 'type'])
            ->defaultSort('-scheduled_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create activity with optional reminder
     */
    public function createActivity(array $data, User $user): LeadActivity
    {
        $activity = LeadActivity::create([
            'lead_id' => $data['leadId'],
            'user_id' => $user->id,
            'type' => $data['type'],
            'status' => ActivityStatus::SCHEDULED,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scheduled_at' => $data['scheduledAt'] ?? null,
            'next_action' => $data['nextAction'] ?? null,
            'reminder_at' => $data['reminderAt'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Create reminder if scheduled
        if ($activity->scheduled_at) {
            $this->createActivityReminder($activity, $user);
        }

        return $activity;
    }

    /**
     * Update activity
     */
    public function updateActivity(LeadActivity $activity, array $data, User $user): LeadActivity
    {
        $activity->update([
            'title' => $data['title'] ?? $activity->title,
            'description' => $data['description'] ?? $activity->description,
            'scheduled_at' => $data['scheduledAt'] ?? $activity->scheduled_at,
            'next_action' => $data['nextAction'] ?? $activity->next_action,
            'reminder_at' => $data['reminderAt'] ?? $activity->reminder_at,
            'updated_by' => $user->id,
        ]);

        return $activity->fresh();
    }

    /**
     * Complete activity with outcome
     */
    public function completeActivity(LeadActivity $activity, array $data, User $user): LeadActivity
    {
        $activity->update([
            'status' => ActivityStatus::COMPLETED,
            'outcome' => $data['outcome'],
            'next_action' => $data['nextAction'] ?? null,
            'reminder_at' => $data['reminderAt'] ?? null,
            'completed_at' => now(),
            'updated_by' => $user->id,
        ]);

        // Update lead last contact
        $activity->lead->update(['last_contact_at' => now()]);

        return $activity->fresh();
    }

    /**
     * Cancel activity
     */
    public function cancelActivity(LeadActivity $activity, string $reason, User $user): LeadActivity
    {
        $activity->update([
            'status' => ActivityStatus::CANCELLED,
            'cancellation_reason' => $reason,
            'updated_by' => $user->id,
        ]);

        // Cancel pending reminders
        $activity->reminders()->where('is_sent', false)->delete();

        return $activity->fresh();
    }

    /**
     * Get paginated meetings with filters
     */
    public function getMeetings(array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(LeadMeeting::class)
            ->with('lead')
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('location'),
                AllowedFilter::exact('lead_id'),
                AllowedFilter::scope('scheduled'),
                AllowedFilter::scope('needs_minutes'),
                AllowedFilter::scope('upcoming'),
                AllowedFilter::scope('today'),
            ])
            ->allowedSorts(['scheduled_at', 'created_at'])
            ->defaultSort('scheduled_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create meeting with reminders
     */
    public function createMeeting(array $data, User $user): LeadMeeting
    {
        $meeting = LeadMeeting::create([
            'lead_id' => $data['leadId'],
            'title' => $data['title'],
            'location' => $data['location'],
            'status' => MeetingStatus::SCHEDULED,
            'meeting_link' => $data['meetingLink'] ?? null,
            'scheduled_at' => $data['scheduledAt'],
            'duration_minutes' => $data['durationMinutes'] ?? 60,
            'attendees' => $data['attendees'] ?? [],
            'client_attendees' => $data['clientAttendees'] ?? [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Create reminders for all attendees
        $this->createMeetingReminders($meeting, $user);

        return $meeting;
    }

    /**
     * Update meeting
     */
    public function updateMeeting(LeadMeeting $meeting, array $data, User $user): LeadMeeting
    {
        $meeting->update([
            'title' => $data['title'] ?? $meeting->title,
            'location' => $data['location'] ?? $meeting->location,
            'meeting_link' => $data['meetingLink'] ?? $meeting->meeting_link,
            'scheduled_at' => $data['scheduledAt'] ?? $meeting->scheduled_at,
            'duration_minutes' => $data['durationMinutes'] ?? $meeting->duration_minutes,
            'attendees' => $data['attendees'] ?? $meeting->attendees,
            'client_attendees' => $data['clientAttendees'] ?? $meeting->client_attendees,
            'updated_by' => $user->id,
        ]);

        // Update reminders if time changed
        if (isset($data['scheduledAt'])) {
            $this->updateMeetingReminders($meeting);
        }

        return $meeting->fresh();
    }

    /**
     * Complete meeting with minutes
     */
    public function completeMeeting(LeadMeeting $meeting, array $data, User $user): LeadMeeting
    {
        $meeting->update([
            'status' => MeetingStatus::COMPLETED,
            'minutes' => $data['minutes'],
            'outcome' => $data['outcome'] ?? null,
            'attachments' => $data['attachments'] ?? $meeting->attachments,
            'updated_by' => $user->id,
        ]);

        // Update lead last contact
        $meeting->lead->update(['last_contact_at' => now()]);

        return $meeting->fresh();
    }

    /**
     * Cancel meeting
     */
    public function cancelMeeting(LeadMeeting $meeting, string $reason, User $user): LeadMeeting
    {
        $meeting->update([
            'status' => MeetingStatus::CANCELLED,
            'cancellation_reason' => $reason,
            'updated_by' => $user->id,
        ]);

        // Cancel pending reminders
        $meeting->reminders()->where('is_sent', false)->delete();

        // TODO: Send cancellation notification to attendees

        return $meeting->fresh();
    }

    /**
     * Get today's schedule for user
     */
    public function getTodaySchedule(User $user): Collection
    {
        $activities = LeadActivity::with('lead')
            ->where('user_id', $user->id)
            ->whereDate('scheduled_at', today())
            ->where('status', ActivityStatus::SCHEDULED)
            ->get();

        $meetings = LeadMeeting::with('lead')
            ->whereDate('scheduled_at', today())
            ->where('status', MeetingStatus::SCHEDULED)
            ->whereJsonContains('attendees', ['userId' => $user->id])
            ->orWhereJsonContains('attendees', ['user_id' => $user->id])
            ->get();

        return collect([
            'activities' => $activities,
            'meetings' => $meetings,
        ]);
    }

    /**
     * Get upcoming events for user
     */
    public function getUpcomingEvents(User $user, int $days = 7): Collection
    {
        $activities = LeadActivity::with('lead')
            ->where('user_id', $user->id)
            ->whereBetween('scheduled_at', [now(), now()->addDays($days)])
            ->where('status', ActivityStatus::SCHEDULED)
            ->orderBy('scheduled_at')
            ->get();

        $meetings = LeadMeeting::with('lead')
            ->whereBetween('scheduled_at', [now(), now()->addDays($days)])
            ->where('status', MeetingStatus::SCHEDULED)
            ->orderBy('scheduled_at')
            ->get();

        return collect([
            'activities' => $activities,
            'meetings' => $meetings,
        ]);
    }

    /**
     * Create activity reminder (15 min before)
     */
    private function createActivityReminder(LeadActivity $activity, User $user): void
    {
        ActivityReminder::create([
            'remindable_type' => LeadActivity::class,
            'remindable_id' => $activity->id,
            'user_id' => $user->id,
            'type' => ReminderType::BEFORE_EVENT,
            'minutes_offset' => 15,
            'remind_at' => $activity->scheduled_at->subMinutes(15),
            'channel' => 'both',
            'title' => __('activities.reminder_title'),
            'message' => __('activities.reminder_message', ['title' => $activity->title]),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Create meeting reminders for all attendees
     */
    private function createMeetingReminders(LeadMeeting $meeting, User $creator): void
    {
        $attendeeIds = collect($meeting->attendees)->pluck('userId')->filter();

        foreach ($attendeeIds as $userId) {
            // 15 min before reminder
            ActivityReminder::create([
                'remindable_type' => LeadMeeting::class,
                'remindable_id' => $meeting->id,
                'user_id' => $userId,
                'type' => ReminderType::BEFORE_EVENT,
                'minutes_offset' => 15,
                'remind_at' => $meeting->scheduled_at->subMinutes(15),
                'channel' => 'both',
                'title' => __('meetings.reminder_title'),
                'message' => __('meetings.reminder_message', ['title' => $meeting->title]),
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);

            // 1 hour after reminder (for outcome)
            ActivityReminder::create([
                'remindable_type' => LeadMeeting::class,
                'remindable_id' => $meeting->id,
                'user_id' => $userId,
                'type' => ReminderType::AFTER_EVENT,
                'minutes_offset' => 60,
                'remind_at' => $meeting->end_time->addHour(),
                'channel' => 'database',
                'title' => __('meetings.outcome_reminder_title'),
                'message' => __('meetings.outcome_reminder_message', ['title' => $meeting->title]),
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);
        }
    }

    /**
     * Update meeting reminders when time changes
     */
    private function updateMeetingReminders(LeadMeeting $meeting): void
    {
        // Update before-event reminders
        $meeting->reminders()
            ->where('type', ReminderType::BEFORE_EVENT)
            ->where('is_sent', false)
            ->update(['remind_at' => $meeting->scheduled_at->subMinutes(15)]);

        // Update after-event reminders
        $meeting->reminders()
            ->where('type', ReminderType::AFTER_EVENT)
            ->where('is_sent', false)
            ->update(['remind_at' => $meeting->end_time->addHour()]);
    }
}
