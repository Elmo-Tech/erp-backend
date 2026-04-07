<?php

namespace App\Http\Resources\V1\Tasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'acceptanceCriteria' => $this->acceptance_criteria ?? '',
            'status' => $this->status->value,
            'submissionNotes' => $this->submission_notes ?? '',
            'reviewNotes' => $this->review_notes ?? '',
            'submittedAt' => $this->submitted_at?->toISOString(),
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'reviewedBy' => $this->whenLoaded('reviewer', function () {
                return $this->reviewer ? [
                    'userId' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                ] : null;
            }),
            'pointsAwarded' => $this->points_awarded ?? 0,
            'isBonus' => $this->is_bonus ?? false,
            'order' => $this->order ?? 0,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}
