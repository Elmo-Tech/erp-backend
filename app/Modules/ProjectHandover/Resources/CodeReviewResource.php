<?php

namespace App\Modules\ProjectHandover\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodeReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reviewId' => $this->id,
            'taskId' => $this->task_id,
            'taskTitle' => $this->whenLoaded('task', fn () => $this->task->title, ''),
            'reviewerId' => $this->reviewer_id,
            'reviewerName' => $this->whenLoaded('reviewer', fn () => $this->reviewer->name, ''),
            'developerId' => $this->developer_id,
            'developerName' => $this->whenLoaded('developer', fn () => $this->developer->name, ''),
            'scores' => [
                'codeQuality' => (int) ($this->code_quality_score ?? 0),
                'readability' => (int) ($this->readability_score ?? 0),
                'scalability' => (int) ($this->scalability_score ?? 0),
                'testing' => (int) ($this->testing_score ?? 0),
                'documentation' => (int) ($this->documentation_score ?? 0),
                'average' => (float) ($this->average_score ?? 0),
            ],
            'reviewNotes' => $this->review_notes ?? '',
            'improvementSuggestions' => $this->improvement_suggestions ?? '',
            'status' => $this->status ?? '',
            'reviewedAt' => $this->reviewed_at?->toISOString() ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}
