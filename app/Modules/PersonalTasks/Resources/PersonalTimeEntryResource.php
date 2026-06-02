<?php

namespace App\Modules\PersonalTasks\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalTimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'entryId' => $this->id,
            'personalTaskId' => $this->personal_task_id,
            'userId' => $this->user_id,
            'startTime' => $this->start_time?->toISOString(),
            'endTime' => $this->end_time?->toISOString(),
            'durationMinutes' => $this->duration_minutes,
            'isManual' => $this->is_manual,
            'isRunning' => $this->isRunning(),
            'note' => $this->note,
            'task' => $this->whenLoaded('personalTask', fn () => [
                'taskId' => $this->personalTask->id,
                'title' => $this->personalTask->title,
                'status' => $this->personalTask->status,
            ]),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
