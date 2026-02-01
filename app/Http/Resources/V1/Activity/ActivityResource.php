<?php

namespace App\Http\Resources\V1\Activity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ActivityResource",
 *     type="object",
 *     title="Activity Resource",
 *     description="Activity resource representation",
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
        return [
            'activityId' => $this->id,
            'subjectId' => $this->subject_id,
            'subjectType' => $this->subject_type,
            'subjectName' => $this->when($this->relationLoaded('subject'), function () {
                return $this->subject?->name ?? $this->subject?->title ?? 'Unknown';
            }),
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'typeIcon' => $this->type->icon(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statusColor' => $this->status->color(),
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'outcomeSummary' => $this->outcome_summary ?? '',
            'isCompleted' => $this->is_completed ?? false,
            'assignedTo' => $this->when($this->relationLoaded('assignedTo'), [
                'userId' => $this->assignedTo?->id ?? 0,
                'name' => $this->assignedTo?->name ?? '',
                'email' => $this->assignedTo?->email ?? '',
                'avatar' => $this->assignedTo?->avatar ?? '',
            ]),
            'scheduledAt' => $this->scheduled_at?->toISOString() ?? '',
            'completedAt' => $this->completed_at?->toISOString() ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}