<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'milestoneId' => $this->id,
            'projectId' => $this->project_id,
            'title' => $this->title,
            'status' => $this->status?->value,
            'statusLabel' => $this->status?->label(),
            'progressPercentage' => $this->progress_percentage,
            'paymentStageId' => $this->payment_stage_id,
            'invoiceId' => $this->invoice_id,
            'tasks' => ProjectTaskResource::collection($this->whenLoaded('tasks')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
