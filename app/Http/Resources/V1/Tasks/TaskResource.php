<?php

namespace App\Http\Resources\V1\Tasks;

use App\Enums\TaskType;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TaskResource",
 *     type="object",
 *
 *     @OA\Property(property="taskId", type="integer", example=1),
 *     @OA\Property(property="projectId", type="integer", example=1),
 *     @OA\Property(property="parentId", type="integer", nullable=true, example=null),
 *     @OA\Property(property="title", type="string", example="Implement user authentication"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="acceptanceCriteria", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"todo", "in_progress", "review", "testing", "completed"}),
 *     @OA\Property(property="type", type="string", enum={"main", "weekly", "subtask"}),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}),
 *     @OA\Property(property="dueDate", type="string", format="date", nullable=true),
 *     @OA\Property(property="expectedResolutionDate", type="string", format="date", nullable=true),
 *     @OA\Property(property="startDate", type="string", format="date", nullable=true),
 *     @OA\Property(property="sprintNumber", type="integer", nullable=true),
 *     @OA\Property(property="estimatedHours", type="number", format="float", example=8.5),
 *     @OA\Property(property="totalTimeSpent", type="integer", example=120),
 *     @OA\Property(property="reopenCount", type="integer", example=0),
 *     @OA\Property(property="testCompletionPercentage", type="number", format="float", example=75.5),
 *     @OA\Property(property="supervisorId", type="integer", nullable=true, example=3),
 *     @OA\Property(property="supervisor", type="object", nullable=true),
 *     @OA\Property(property="assignee", type="object", nullable=true),
 *     @OA\Property(property="assignees", type="array", @OA\Items(ref="#/components/schemas/TaskAssigneeResource")),
 *     @OA\Property(property="teamAssignees", type="array", @OA\Items(ref="#/components/schemas/TaskAssigneeResource"), description="Available team members from Main Task (for Weekly/Subtasks only)"),
 *     @OA\Property(property="tags", type="array", @OA\Items(ref="#/components/schemas/TagResource")),
 *     @OA\Property(property="children", type="array", @OA\Items(ref="#/components/schemas/TaskResource")),
 *     @OA\Property(property="createdAt", type="string", format="date-time"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time")
 * )
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewInternal = $user && $user->can('view_internal_notes');

        return [
            'taskId' => $this->id,
            'projectId' => $this->project_id,
            'parentId' => $this->parent_id,
            'assignedTo' => $this->assigned_to,
            'supervisorId' => $this->supervisor_id,
            'title' => $this->title,
            'description' => $this->description ?? '',
            'acceptanceCriteria' => $this->acceptance_criteria ?? '',
            'status' => $this->status?->value ?? 'todo',
            'type' => $this->type?->value ?? 'main',
            'priority' => $this->priority?->value ?? 'medium',
            'taskCategory' => $this->task_category?->value,
            'taskCategoryLabel' => $this->task_category?->label() ?? 'No Category',
            'taskCategoryColor' => $this->task_category?->color() ?? 'bg-gray-100 text-gray-800',
            'dueDate' => $this->due_date?->format('Y-m-d'),
            'expectedResolutionDate' => $this->expected_resolution_date?->format('Y-m-d'),
            'startDate' => $this->start_date?->format('Y-m-d'),
            'sprintNumber' => $this->sprint_number,
            'estimatedHours' => (float) ($this->estimated_hours ?? 0),
            'totalTimeSpent' => (int) $this->total_time_spent,
            'reopenCount' => (int) $this->reopen_count,
            'testCompletionPercentage' => $this->getTestCompletionPercentage(),
            'canStartTimer' => $this->canStartTimer(),
            'hasChildren' => $this->hasChildren(),

            // Delivery tracking
            'completedAt' => $this->completed_at?->format('Y-m-d\TH:i:s'),
            'delayDays' => $this->delay_days,
            'deliveryStatus' => $this->getDeliveryStatus(),
            'isOverdue' => $this->isOverdue(),
            'daysUntilDue' => $this->getDaysUntilDue(),

            // Internal notes - only for authorized users
            'internalReviewerNotes' => $canViewInternal ? ($this->internal_reviewer_notes ?? '') : null,
            'holdReason' => $this->hold_reason,
            'isPipelineTask' => (bool) $this->is_pipeline_task,
            'pipelinePosition' => (int) $this->pipeline_position,
            'workflowStageId' => $this->workflow_stage_id,
            'workflowStage' => $this->whenLoaded('workflowStage', fn () => [
                'stageId' => $this->workflowStage->id,
                'name' => $this->workflowStage->name,
                'slug' => $this->workflowStage->slug,
                'color' => $this->workflowStage->color,
                'category' => $this->workflowStage->category,
                'position' => $this->workflowStage->position,
                'isTerminal' => $this->workflowStage->is_terminal,
            ]),

            // Relationships
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'userId' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
                'avatar' => $this->assignee->avatar,
            ] : null),

            'supervisor' => $this->whenLoaded('supervisor', fn () => $this->supervisor ? [
                'userId' => $this->supervisor->id,
                'name' => $this->supervisor->name,
                'avatar' => $this->supervisor->avatar,
            ] : null),

            'assignees' => $this->whenLoaded('assignees', fn () => TaskAssigneeResource::collection($this->assignees)
            ),

            // Team assignees from Main Task (for Weekly/Subtasks to know available team members)
            'teamAssignees' => $this->getTeamAssignees(),

            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)
            ),

            'testScenarios' => $this->whenLoaded('testScenarios', fn () => TaskTestScenarioResource::collection($this->testScenarios)
            ),

            'children' => $this->whenLoaded('children', fn () => TaskResource::collection($this->children)
            ),

            'deliverables' => $this->whenLoaded('deliverables', fn () => TaskDeliverableResource::collection($this->deliverables)
            ),

            'project' => $this->whenLoaded('project', fn () => [
                'projectId' => $this->project->id,
                'title' => $this->project->title,
            ]),

            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'taskId' => $this->parent->id,
                'title' => $this->parent->title,
            ] : null),

            'createdAt' => $this->created_at?->format('Y-m-d\TH:i:s') ?? '',
            'updatedAt' => $this->updated_at?->format('Y-m-d\TH:i:s') ?? '',
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
        ];
    }

    /**
     * Get team assignees from the Main Task for Weekly/Subtasks.
     * Returns null for Main Tasks (they define the team themselves).
     */
    private function getTeamAssignees(): ?array
    {
        // Main Tasks don't need teamAssignees - they ARE the team source
        if ($this->type === TaskType::MAIN) {
            return null;
        }

        // Find the Main Task (root ancestor)
        $mainTask = $this->findMainTask();

        if (! $mainTask) {
            return [];
        }

        // Load assignees if not already loaded
        if (! $mainTask->relationLoaded('assignees')) {
            $mainTask->load('assignees.user');
        }

        return TaskAssigneeResource::collection($mainTask->assignees)->resolve();
    }

    /**
     * Find the Main Task (root ancestor) for this task.
     */
    private function findMainTask(): ?Task
    {
        $current = $this->resource;

        // Walk up the parent chain until we find a Main Task or reach the root
        while ($current->parent_id !== null) {
            $parent = Task::find($current->parent_id);

            if (! $parent) {
                break;
            }

            if ($parent->type === TaskType::MAIN) {
                return $parent;
            }

            $current = $parent;
        }

        // If current task's parent is null and it's a Main Task, return it
        if ($current->type === TaskType::MAIN) {
            return $current;
        }

        return null;
    }
}
