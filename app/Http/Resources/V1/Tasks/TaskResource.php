<?php

namespace App\Http\Resources\V1\Tasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * 
     * Transforms all fields to camelCase format.
     * Conditionally includes internalReviewerNotes based on view_internal_notes permission.
     * 
     * Requirements: 4.2, 4.3, 4.4, 8.4
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'taskId' => $this->id ?? 0,
            'projectId' => $this->project_id ?? 0,
            'parentId' => $this->parent_id,
            'assignedTo' => $this->assigned_to,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'estimatedHours' => (float) ($this->estimated_hours ?? 0.0),
            'totalTimeSpent' => $this->total_time_spent ?? 0,
            'reopenCount' => $this->reopen_count ?? 0,
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            // Include assignee relationship when loaded
            'assignee' => $this->whenLoaded('assignee', function () {
                return [
                    'userId' => $this->assignee->id ?? 0,
                    'name' => $this->assignee->name ?? '',
                    'email' => $this->assignee->email ?? '',
                ];
            }),
            // Include children relationship when loaded (for recursive hierarchy)
            'children' => $this->whenLoaded('children', function () {
                return TaskResource::collection($this->children);
            }),
            // Include parent relationship when loaded
            'parent' => $this->whenLoaded('parent', function () {
                return new TaskResource($this->parent);
            }),
            // Include project relationship when loaded
            'project' => $this->whenLoaded('project', function () {
                return [
                    'projectId' => $this->project->id ?? 0,
                    'title' => $this->project->title ?? '',
                ];
            }),
        ];

        // Conditionally include internal notes based on permission
        // Requirements: 4.2, 4.3 - Only include if user has view_internal_notes permission
        if ($request->user()?->can('view_internal_notes')) {
            $data['internalReviewerNotes'] = $this->internal_reviewer_notes ?? '';
        }

        return $data;
    }
}
