<?php

namespace App\Http\Resources\V1\TaskDeliverables;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TaskDeliverableResource",
 *     type="object",
 *     title="Task Deliverable Resource",
 *     description="Task deliverable resource representation",
 *     @OA\Property(property="deliverableId", type="integer", example=1),
 *     @OA\Property(property="taskId", type="integer", example=5),
 *     @OA\Property(property="title", type="string", example="Complete user authentication"),
 *     @OA\Property(property="description", type="string", example="Implement login and registration functionality"),
 *     @OA\Property(property="acceptanceCriteria", type="string", example="- Login form validation\n- Password encryption\n- Session management"),
 *     @OA\Property(property="status", type="string", enum={"pending", "submitted", "under_review", "approved", "rejected"}, example="pending"),
 *     @OA\Property(property="submissionNotes", type="string", nullable=true, example="Completed all requirements"),
 *     @OA\Property(property="reviewNotes", type="string", nullable=true, example="Excellent work!"),
 *     @OA\Property(property="submittedAt", type="string", format="date-time", nullable=true, example="2026-01-25T10:30:00Z"),
 *     @OA\Property(property="reviewedAt", type="string", format="date-time", nullable=true, example="2026-01-25T14:15:00Z"),
 *     @OA\Property(property="pointsAwarded", type="integer", example=1),
 *     @OA\Property(property="isBonus", type="boolean", example=false),
 *     @OA\Property(property="order", type="integer", example=1),
 *     @OA\Property(property="statusColor", type="string", example="gray"),
 *     @OA\Property(property="canBeSubmitted", type="boolean", example=true),
 *     @OA\Property(property="canBeReviewed", type="boolean", example=false),
 *     @OA\Property(property="isCompleted", type="boolean", example=false),
 *     @OA\Property(
 *         property="reviewer",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="userId", type="integer", example=2),
 *         @OA\Property(property="name", type="string", example="John Reviewer"),
 *         @OA\Property(property="email", type="string", example="reviewer@example.com"),
 *         @OA\Property(property="avatar", type="string", example="")
 *     ),
 *     @OA\Property(property="createdAt", type="string", format="date-time", example="2026-01-25T09:00:00Z"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time", example="2026-01-25T10:30:00Z")
 * )
 */
class TaskDeliverableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'deliverableId' => $this->id,
            'taskId' => $this->task_id,
            'title' => $this->title,
            'description' => $this->description ?? "",
            'acceptanceCriteria' => $this->acceptance_criteria ?? "",
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statusColor' => $this->getStatusColor(),
            'submissionNotes' => $this->submission_notes ?? "",
            'reviewNotes' => $this->review_notes ?? "",
            'submittedAt' => $this->submitted_at?->toISOString(),
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'pointsAwarded' => $this->points_awarded,
            'isBonus' => $this->is_bonus,
            'order' => $this->order,
            
            // Status helpers
            'canBeSubmitted' => $this->canBeSubmitted(),
            'canBeReviewed' => $this->canBeReviewed(),
            'isCompleted' => $this->isCompleted(),
            
            // Relationships
            'reviewer' => $this->when($this->reviewer, function () {
                return [
                    'userId' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                    'email' => $this->reviewer->email,
                    'avatar' => $this->reviewer->avatar,
                ];
            }),
            
            // Timestamps
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}