<?php

namespace App\Modules\Project\Resources;

use App\Modules\Project\Models\ProjectWorkflowStage;
use App\Modules\Settings\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @property string|null $cover_image_path
 * @property int|null $workflow_stage_id
 * @property int $pipeline_position
 * @property-read Collection<int, User> $assignees
 * @property-read ProjectWorkflowStage|null $workflowStage
 */
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
            'coverImageUrl' => $this->cover_image_path
                ? Storage::disk('public')->url($this->cover_image_path)
                : null,
            'status' => $this->status?->value,
            'statusLabel' => $this->status?->label(),
            'priority' => $this->priority,
            'estimatedHours' => $this->estimated_hours !== null ? (float) $this->estimated_hours : null,
            'startDate' => $this->start_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'assigneeId' => $this->assignee_id,
            'assigneeIds' => $this->whenLoaded(
                'assignees',
                fn () => $this->assignees->pluck('id')->values()
            ),
            'workflowStageId' => $this->workflow_stage_id,
            'pipelinePosition' => $this->pipeline_position,
            'isInBacklog' => $this->isInBacklog(),
            'totalLoggedMinutes' => $this->totalLoggedMinutes(),
            'allowedActions' => $this->allowedActions($request),

            // Eager-loaded relations — only present when explicitly loaded
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'userId' => $this->assignee->id,
                'name' => $this->assignee->name,
                'avatar' => $this->assignee->avatar,
            ]),
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(
                fn ($assignee): array => [
                    'userId' => $assignee->id,
                    'name' => $assignee->name,
                    'avatar' => $assignee->avatar,
                ]
            )->values()),
            'workflowStage' => $this->whenLoaded(
                'workflowStage',
                fn () => $this->workflowStage ? [
                    'stageId' => $this->workflowStage->id,
                    'name' => $this->workflowStage->name,
                    'status' => $this->workflowStage->status->value,
                    'color' => $this->workflowStage->color,
                    'position' => $this->workflowStage->position,
                    'isTerminal' => $this->workflowStage->is_terminal,
                ] : null
            ),
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

    /** @return array<string, bool> */
    private function allowedActions(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [
                'edit' => false,
                'delete' => false,
                'execute' => false,
                'startTimer' => false,
                'stopTimer' => false,
                'changeStatus' => false,
                'complete' => false,
                'reopen' => false,
                'createDeliverable' => false,
                'editDeliverable' => false,
                'deleteDeliverable' => false,
                'toggleDeliverable' => false,
            ];
        }

        return app(TaskPolicy::class)->allowedActions($user, $this->resource);
    }
}
