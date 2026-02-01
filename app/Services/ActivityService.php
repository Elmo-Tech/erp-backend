<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\User;
use App\Notifications\InnovationLogSubmitted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class ActivityService
{
    /**
     * Log an activity for any subject (Lead, Project, etc.)
     */
    public function logActivity(Model $subject, array $data, User $user): Activity
    {
        $activity = Activity::create([
            'subject_id' => $subject->id,
            'subject_type' => get_class($subject),
            'type' => $data['type'],
            'status' => $data['status'] ?? 'scheduled',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'outcome_summary' => $data['outcomeSummary'] ?? null,
            'is_completed' => $data['isCompleted'] ?? false,
            'assigned_to' => $data['assignedTo'] ?? $user->id,
            'scheduled_at' => $data['scheduledAt'] ?? null,
            'completed_at' => $data['completedAt'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Handle special activity types
        $this->handleSpecialActivityTypes($activity, $subject, $user);

        return $activity;
    }

    /**
     * Mark activity as complete with outcome
     */
    public function markAsComplete(Activity $activity, string $outcomeSummary, User $user): Activity
    {
        $activity->update([
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
            'outcome_summary' => $outcomeSummary,
            'updated_by' => $user->id,
        ]);

        return $activity->fresh();
    }

    /**
     * Schedule a new activity
     */
    public function scheduleActivity(Model $subject, array $data, User $user): Activity
    {
        return $this->logActivity($subject, array_merge($data, [
            'status' => 'scheduled',
            'is_completed' => false,
        ]), $user);
    }

    /**
     * Cancel an activity
     */
    public function cancelActivity(Activity $activity, string $reason, User $user): Activity
    {
        $activity->update([
            'status' => 'cancelled',
            'outcome_summary' => "Cancelled: {$reason}",
            'updated_by' => $user->id,
        ]);

        return $activity->fresh();
    }

    /**
     * Get activities for a subject
     */
    public function getActivitiesForSubject(Model $subject, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Activity::where('subject_id', $subject->id)
            ->where('subject_type', get_class($subject))
            ->with(['assignedTo'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['assignedTo'])) {
            $query->where('assigned_to', $filters['assignedTo']);
        }

        return $query->get();
    }

    /**
     * Get upcoming activities for a user
     */
    public function getUpcomingActivities(User $user, int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return Activity::where('assigned_to', $user->id)
            ->upcoming($hours)
            ->with(['subject'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Get activities needing outcome
     */
    public function getActivitiesNeedingOutcome(User $user = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Activity::needsOutcome()->with(['subject', 'assignedTo']);

        if ($user) {
            $query->where('assigned_to', $user->id);
        }

        return $query->orderBy('completed_at')->get();
    }

    /**
     * Handle special activity types (like technical_discovery)
     */
    private function handleSpecialActivityTypes(Activity $activity, Model $subject, User $user): void
    {
        if ($activity->type === ActivityType::MEETING && 
            str_contains(strtolower($activity->title), 'technical') || 
            str_contains(strtolower($activity->description ?? ''), 'technical')) {
            
            $this->notifyTechnicalTeam($activity, $subject, $user);
        }
    }

    /**
     * Notify CTO/Tech Lead about technical discovery activities
     */
    private function notifyTechnicalTeam(Activity $activity, Model $subject, User $user): void
    {
        // Get users with CTO or Tech Lead roles
        $technicalLeads = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['cto', 'tech_lead', 'technical_lead']);
        })->get();

        if ($technicalLeads->isNotEmpty()) {
            // Create a notification about the technical discovery activity
            $notificationData = [
                'title' => 'Technical Discovery Activity Scheduled',
                'message' => "A technical discovery activity has been scheduled: {$activity->title}",
                'activity_id' => $activity->id,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->id,
                'scheduled_by' => $user->name,
                'scheduled_at' => $activity->scheduled_at?->format('Y-m-d H:i'),
            ];

            // Send notification to technical leads
            Notification::send($technicalLeads, new InnovationLogSubmitted($notificationData));
        }
    }

    /**
     * Create a quick note activity
     */
    public function addNote(Model $subject, string $note, User $user): Activity
    {
        return $this->logActivity($subject, [
            'type' => ActivityType::NOTE,
            'title' => 'Note Added',
            'description' => $note,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ], $user);
    }

    /**
     * Log a call activity
     */
    public function logCall(Model $subject, array $data, User $user): Activity
    {
        return $this->logActivity($subject, array_merge($data, [
            'type' => ActivityType::CALL,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]), $user);
    }

    /**
     * Log an email activity
     */
    public function logEmail(Model $subject, array $data, User $user): Activity
    {
        return $this->logActivity($subject, array_merge($data, [
            'type' => ActivityType::EMAIL,
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]), $user);
    }

    /**
     * Schedule a meeting activity
     */
    public function scheduleMeeting(Model $subject, array $data, User $user): Activity
    {
        return $this->logActivity($subject, array_merge($data, [
            'type' => ActivityType::MEETING,
            'status' => 'scheduled',
            'is_completed' => false,
        ]), $user);
    }
}