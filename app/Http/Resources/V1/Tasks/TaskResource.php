<?php

namespace App\Http\Resources\V1\Tasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TaskResource",
 *     type="object",
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
 *     @OA\Property(property="startDate", type="string", format="date", nullable=true),
 *     @OA\Property(property="sprintNumber", type="integer", nullable=true),
 *     @OA\Property(property="estimatedHours", type="number", format="float", example=8.5),
 *     @OA\Property(property="totalTimeSpent", type="integer", example=120),
 *     @OA\Property(property="reopenCount", type="integer", example=0),
 *     @OA\Property(property="testCompletionPercentage", type="number", format="float", example=75.5),
 *     @OA\Property(property="assignee", type="object", nullable=true),
 *     @OA\Property(property="assignees", type="array", @OA\Items(ref="#/components/schemas/TaskAssigneeResource")),
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
            'title' => $this->title,
            'description' => $this->description ?? '',
            'acceptanceCriteria' => $this->acceptance_criteria ?? '',
            'status' => $this->status?->value ?? 'todo',
            'type' => $this->type?->value ?? 'main',
            'priority' => $this->priority?->value ?? 'medium',
            'dueDate' => $this->due_date?->format('Y-m-d'),
            'startDate' => $this->start_date?->format('Y-m-d'),
            'sprintNumber' => $this->sprint_number,
            'estimatedHours' => (float) ($this->estimated_hours ?? 0),
            'totalTimeSpent' => (int) $this->total_time_spent,
            'reopenCount' => (int) $this->reopen_count,
            'testCompletionPercentage' => $this->getTestCompletionPercentage(),
            
            // Internal notes - only for authorized users
            'internalReviewerNotes' => $canViewInternal ? ($this->internal_reviewer_notes ?? '') : null,
            
            // Relationships
            'assignee' => $this->whenLoaded('assignee', fn() => $this->assignee ? [
                'userId' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
                'avatar' => $this->assignee->avatar ?? '',
            ] : null),
            
            'assignees' => $this->whenLoaded('assignees', fn() => 
                TaskAssigneeResource::collection($this->assignees)
            ),
            
            'tags' => $this->whenLoaded('tags', fn() => 
                TagResource::collection($this->tags)
            ),
            
            'testScenarios' => $this->whenLoaded('testScenarios', fn() => 
                TaskTestScenarioResource::collection($this->testScenarios)
            ),
            
            'children' => $this->whenLoaded('children', fn() => 
                TaskResource::collection($this->children)
            ),
            
            'project' => $this->whenLoaded('project', fn() => [
                'projectId' => $this->project->id,
                'title' => $this->project->title,
            ]),
            
            'parent' => $this->whenLoaded('parent', fn() => $this->parent ? [
                'taskId' => $this->parent->id,
                'title' => $this->parent->title,
            ] : null),
            
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
        ];
    }
}
