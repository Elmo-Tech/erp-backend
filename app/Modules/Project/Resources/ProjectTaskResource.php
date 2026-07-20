<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'taskId' => $this->id,
            'projectId' => $this->project_id,
            'milestoneId' => $this->milestone_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'statusLabel' => $this->status?->label(),
            'priority' => $this->priority,
            'estimatedHours' => $this->estimated_hours !== null ? (float) $this->estimated_hours : null,
            'startDate' => $this->start_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'assigneeId' => $this->assignee_id,
            'isInBacklog' => $this->isInBacklog(),
            'totalLoggedMinutes' => $this->totalLoggedMinutes(),

            // Eager-loaded relations — only present when explicitly loaded
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'userId' => $this->assignee->id,
                'name' => $this->assignee->name,
                'avatar' => $this->assignee->avatar,
            ]),
            'milestone' => $this->whenLoaded('milestone', fn () => [
                'milestoneId' => $this->milestone->id,
                'title' => $this->milestone->title,
                'status' => $this->milestone->status?->value,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                'projectId' => $this->project->id,
                'title' => $this->project->title,
                'client' => $this->project->relationLoaded('client') && $this->project->client ? [
                    'clientId' => $this->project->client->id,
                    'name' => $this->project->client->name,
                ] : null,
            ]),
            'deliverables' => ProjectTaskDeliverableResource::collection($this->whenLoaded('deliverables')),
            'timeEntries' => ProjectTimeEntryResource::collection($this->whenLoaded('timeEntries')),

            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
