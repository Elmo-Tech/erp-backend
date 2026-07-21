<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Actions\ProjectHistoryAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTaskDeliverable;
use App\Modules\Project\Requests\StoreTaskDeliverableRequest;
use App\Modules\Project\Resources\ProjectTaskDeliverableResource;
use Illuminate\Http\JsonResponse;

class ProjectTaskDeliverableController extends Controller
{
    public function __construct(
        private readonly ProjectHistoryAction $historyAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * POST /projects/{project}/tasks/{task}/deliverables
     *
     * SECURITY: Requires the granular create permission or a management override.
     */
    public function store(StoreTaskDeliverableRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->authorize('view', $project);
        $this->assertTaskBelongsToProject($task, $project);

        $this->authorize('createDeliverable', $task);

        $deliverable = $task->deliverables()->create([
            'title' => $request->validated()['title'],
            'is_completed' => false,
        ]);

        // Record activity for deliverable creation
        $this->historyAction->recordChange(
            $project,
            'deliverable_added',
            '',
            $deliverable->title,
            "Deliverable added to task '{$task->title}'"
        );

        return ApiResponse::success(
            new ProjectTaskDeliverableResource($deliverable),
            'Deliverable added successfully',
            201
        );
    }

    /**
     * PATCH /projects/{project}/tasks/{task}/deliverables/{deliverable}
     * Rename a deliverable.
     */
    public function update(StoreTaskDeliverableRequest $request, Project $project, ProjectTask $task, ProjectTaskDeliverable $deliverable): JsonResponse
    {
        $this->authorize('view', $project);
        $this->assertTaskBelongsToProject($task, $project);
        $this->assertDeliverableBelongsToTask($deliverable, $task);

        $this->authorize('editDeliverable', $task);

        $oldTitle = $deliverable->title;
        $deliverable->update(['title' => $request->validated()['title']]);
        $deliverable->refresh();

        $this->historyAction->recordChange(
            $project,
            'deliverable_updated',
            $oldTitle,
            $deliverable->title,
            "Deliverable renamed in task '{$task->title}'"
        );

        return ApiResponse::success(
            new ProjectTaskDeliverableResource($deliverable),
            'Deliverable updated successfully'
        );
    }

    /**
     * PATCH /projects/{project}/tasks/{task}/deliverables/{deliverable}/toggle
     * Toggles the is_completed flag on a single deliverable.
     */
    public function toggle(Project $project, ProjectTask $task, ProjectTaskDeliverable $deliverable): JsonResponse
    {
        $this->authorize('view', $project);
        $this->assertTaskBelongsToProject($task, $project);
        $this->assertDeliverableBelongsToTask($deliverable, $task);
        $this->authorize('toggleDeliverable', $task);

        $oldStatus = $deliverable->is_completed;
        $deliverable->update(['is_completed' => ! $deliverable->is_completed]);
        $deliverable->refresh();

        // Record activity for deliverable toggle
        $this->historyAction->recordChange(
            $project,
            'deliverable_toggled',
            $oldStatus ? 'completed' : 'incomplete',
            $deliverable->is_completed ? 'completed' : 'incomplete',
            "Deliverable '{$deliverable->title}' in task '{$task->title}' marked as ".($deliverable->is_completed ? 'complete' : 'incomplete')
        );

        return ApiResponse::success(
            new ProjectTaskDeliverableResource($deliverable),
            $deliverable->is_completed ? 'Deliverable marked complete' : 'Deliverable marked incomplete'
        );
    }

    /**
     * DELETE /projects/{project}/tasks/{task}/deliverables/{deliverable}
     *
     * SECURITY: Requires the granular delete permission or a management override.
     */
    public function destroy(Project $project, ProjectTask $task, ProjectTaskDeliverable $deliverable): JsonResponse
    {
        $this->authorize('view', $project);
        $this->assertTaskBelongsToProject($task, $project);
        $this->assertDeliverableBelongsToTask($deliverable, $task);
        $this->authorize('deleteDeliverable', $task);

        $deliverable->delete();

        return ApiResponse::success(null, 'Deliverable deleted successfully');
    }

    // -------------------------------------------------------------------------

    private function assertTaskBelongsToProject(ProjectTask $task, Project $project): void
    {
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found for this project.');
        }
    }

    private function assertDeliverableBelongsToTask(ProjectTaskDeliverable $deliverable, ProjectTask $task): void
    {
        if ($deliverable->task_id !== $task->id) {
            abort(404, 'Deliverable not found for this task.');
        }
    }
}
