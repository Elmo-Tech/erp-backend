<?php

namespace App\Modules\Meetings\Resources;

use App\Modules\Client\Models\Client;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $meeting = $this->resource;

        return [
            'meetingId' => $meeting->id,
            'subjectId' => $meeting->subject_id,
            'subjectType' => $meeting->subject_type,
            'subjectName' => $this->when($meeting->relationLoaded('subject'), function () use ($meeting) {
                $subject = $meeting->subject;

                if ($subject instanceof Client) {
                    return $subject->display_name ?? 'Unknown';
                }

                if ($subject instanceof Project) {
                    return $subject->title;
                }

                return 'Unknown';
            }),
            'status' => $meeting->status->value,
            'statusLabel' => $meeting->status->label(),
            'statusColor' => $meeting->status->color(),
            'title' => $meeting->title ?? '',
            'description' => $meeting->description ?? '',
            'location' => $meeting->location?->value,
            'locationDetails' => $meeting->location_details ?? '',
            'outcomeSummary' => $meeting->outcome_summary ?? '',
            'isCompleted' => $meeting->is_completed ?? false,
            'assignedTo' => $this->when($meeting->relationLoaded('assignedTo'), function () use ($meeting) {
                $assignedTo = $meeting->assignedTo;

                if ($assignedTo === null) {
                    return [
                        'userId' => 0,
                        'name' => '',
                        'email' => '',
                        'avatar' => '',
                    ];
                }

                return [
                    'userId' => $assignedTo->id,
                    'name' => $assignedTo->name ?? '',
                    'email' => $assignedTo->email ?? '',
                    'avatar' => $assignedTo->avatar ?? '',
                ];
            }),
            'scheduledAt' => $meeting->scheduled_at?->toISOString() ?? '',
            'completedAt' => $meeting->completed_at?->toISOString() ?? '',
            'createdAt' => $meeting->created_at?->toISOString() ?? '',
            'updatedAt' => $meeting->updated_at?->toISOString() ?? '',
        ];
    }
}
