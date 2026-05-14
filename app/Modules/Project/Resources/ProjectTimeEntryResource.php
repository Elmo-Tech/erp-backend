<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'entryId'         => $this->id,
            'taskId'          => $this->task_id,
            'projectId'       => $this->whenLoaded('task', fn () => $this->task?->project_id),
            'userId'          => $this->user_id,
            'startTime'       => $this->start_time?->toISOString(),
            'endTime'         => $this->end_time?->toISOString(),
            'durationMinutes' => $this->duration_minutes,
            'isManual'        => $this->is_manual,
            'isRunning'       => $this->isRunning(),
            'note'            => $this->note,
            'user'            => $this->whenLoaded('user', fn () => [
                'userId' => $this->user->id,
                'name'   => $this->user->name,
                'avatar' => $this->user->avatar,
            ]),
            'task'            => $this->whenLoaded('task', fn () => [
                'taskId' => $this->task->id,
                'projectId' => $this->task->project_id,
                'title' => $this->task->title,
                'status' => $this->task->status?->value,
            ]),
            'project'         => $this->whenLoaded('task', fn () => $this->task->relationLoaded('project') && $this->task->project ? [
                'projectId' => $this->task->project->id,
                'title' => $this->task->project->title,
            ] : null),
            'createdAt'       => $this->created_at?->toISOString(),
            'updatedAt'       => $this->updated_at?->toISOString(),
        ];
    }
}
