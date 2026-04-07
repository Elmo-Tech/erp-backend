<?php

namespace App\Http\Resources\V1\Activity;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Activity
 *
 * @OA\Schema(
 *     schema="ActivityResource",
 *     type="object",
 *     title="Activity Resource",
 *     description="Activity resource representation",
 *
 *     @OA\Property(property="activityId", type="integer", example=1),
 *     @OA\Property(property="subjectId", type="integer", example=1),
 *     @OA\Property(property="subjectType", type="string", example="App\\Models\\Lead"),
 *     @OA\Property(property="type", type="string", example="meeting"),
 *     @OA\Property(property="status", type="string", example="scheduled"),
 *     @OA\Property(property="title", type="string", example="Technical Discovery Meeting"),
 *     @OA\Property(property="description", type="string", example="Discuss technical requirements"),
 *     @OA\Property(property="outcomeSummary", type="string", example="Meeting completed successfully"),
 *     @OA\Property(property="isCompleted", type="boolean", example=false),
 *     @OA\Property(property="assignedTo", type="object",
 *         @OA\Property(property="userId", type="integer", example=2),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", example="john@example.com")
 *     ),
 *     @OA\Property(property="scheduledAt", type="string", format="date-time", example="2026-02-01T14:00:00Z"),
 *     @OA\Property(property="completedAt", type="string", format="date-time", example="2026-02-01T15:00:00Z"),
 *     @OA\Property(property="createdAt", type="string", format="date-time", example="2026-02-01T10:00:00Z"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time", example="2026-02-01T10:30:00Z")
 * )
 */
class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;

        return [
            'activityId' => $activity->id,
            'subjectId' => $activity->subject_id,
            'subjectType' => $activity->subject_type,
            'subjectName' => $this->when($activity->relationLoaded('subject'), function () use ($activity) {
                $subject = $activity->subject;

                if ($subject instanceof Lead) {
                    return $subject->name ?? 'Unknown';
                }

                if ($subject instanceof Project) {
                    return $subject->title;
                }

                return 'Unknown';
            }),
            'type' => $activity->type->value,
            'typeLabel' => $activity->type->label(),
            'typeIcon' => $activity->type->icon(),
            'status' => $activity->status->value,
            'statusLabel' => $activity->status->label(),
            'statusColor' => $activity->status->color(),
            'title' => $activity->title ?? '',
            'description' => $activity->description ?? '',
            'outcomeSummary' => $activity->outcome_summary ?? '',
            'isCompleted' => $activity->is_completed ?? false,
            'assignedTo' => $this->when($activity->relationLoaded('assignedTo'), function () use ($activity) {
                $assignedTo = $activity->assignedTo;

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
            'scheduledAt' => $activity->scheduled_at?->toISOString() ?? '',
            'completedAt' => $activity->completed_at?->toISOString() ?? '',
            'createdAt' => $activity->created_at?->toISOString() ?? '',
            'updatedAt' => $activity->updated_at?->toISOString() ?? '',
        ];
    }
}
