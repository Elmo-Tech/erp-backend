<?php

namespace App\Modules\Meetings\Actions;

use App\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Settings\Models\User;

class UpdateMeetingAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Meeting $meeting, array $data, User $user): Meeting
    {
        $status = isset($data['status'])
            ? MeetingStatus::from($data['status'])
            : $meeting->status;
        $isCompleted = $status === MeetingStatus::COMPLETED;

        $meeting->update([
            'title' => $data['title'] ?? $meeting->title,
            'description' => $data['description'] ?? $meeting->description,
            'assigned_to' => $data['assignedTo'] ?? $meeting->assigned_to,
            'scheduled_at' => $data['scheduledAt'] ?? $meeting->scheduled_at,
            'outcome_summary' => $data['outcomeSummary'] ?? $meeting->outcome_summary,
            'status' => $status,
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? ($meeting->completed_at ?? now()) : null,
            'updated_by' => $user->id,
        ]);

        return $meeting->fresh(['subject', 'assignedTo']);
    }
}
