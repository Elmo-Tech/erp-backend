<?php

namespace App\Modules\PersonalTasks\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'taskId' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'dueDate' => $this->due_date?->toDateString(),
            'estimatedMinutes' => $this->estimated_minutes,
            'totalLoggedMinutes' => $this->totalLoggedMinutes(),
            'completedAt' => $this->completed_at?->toISOString(),
            'timeEntries' => PersonalTimeEntryResource::collection($this->whenLoaded('timeEntries')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
