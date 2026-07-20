<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskDeliverableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'deliverableId' => $this->id,
            'taskId' => $this->task_id,
            'title' => $this->title,
            'isCompleted' => $this->is_completed,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
