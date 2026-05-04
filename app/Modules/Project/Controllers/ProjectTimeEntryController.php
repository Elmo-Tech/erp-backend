<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Actions\ToggleTaskTimerAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Project\Requests\StoreManualTimeEntryRequest;
use App\Modules\Project\Resources\ProjectTimeEntryResource;
use Illuminate\Http\JsonResponse;

class ProjectTimeEntryController extends Controller
{
    public function __construct(
        private readonly ToggleTaskTimerAction $toggleTimer,
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /projects/{project}/tasks/{task}/time-entries
     * List all time entries for a task.
     */
    public function index(Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertTaskBelongsToProject($task, $project);

        $entries = $task->timeEntries()
            ->with('user:id,name,avatar')
            ->orderByDesc('start_time')
            ->get();

        return ApiResponse::success(
            ['timeEntries' => ProjectTimeEntryResource::collection($entries)],
            'Time entries retrieved successfully'
        );
    }

    /**
     * POST /projects/{project}/tasks/{task}/time-entries/toggle
     * Start or stop the timer for the authenticated user on this task.
     */
    public function toggle(Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertTaskBelongsToProject($task, $project);

        $entry = $this->toggleTimer->execute($task, request()->user());

        $entry->load('user:id,name,avatar');

        $isStopped = $entry->end_time !== null;

        return ApiResponse::success(
            new ProjectTimeEntryResource($entry),
            $isStopped ? 'Timer stopped' : 'Timer started'
        );
    }

    /**
     * POST /projects/{project}/tasks/{task}/time-entries
     * Log a manual time entry (no timer involved).
     */
    public function store(StoreManualTimeEntryRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertTaskBelongsToProject($task, $project);

        $data = $request->validated();

        $start    = \Carbon\Carbon::parse($data['startTime']);
        $end      = \Carbon\Carbon::parse($data['endTime']);
        $duration = (int) $start->diffInMinutes($end);

        $entry = $task->timeEntries()->create([
            'user_id'          => request()->user()->id,
            'start_time'       => $start,
            'end_time'         => $end,
            'duration_minutes' => $duration,
            'is_manual'        => true,
            'note'             => $data['note'] ?? null,
        ]);

        $entry->load('user:id,name,avatar');

        return ApiResponse::success(
            new ProjectTimeEntryResource($entry),
            'Time entry logged successfully',
            201
        );
    }

    /**
     * DELETE /projects/{project}/tasks/{task}/time-entries/{entry}
     * Remove a time entry (requires edit_time_entries permission).
     */
    public function destroy(Project $project, ProjectTask $task, ProjectTimeEntry $entry): JsonResponse
    {
        $this->assertTaskBelongsToProject($task, $project);
        $this->assertEntryBelongsToTask($entry, $task);

        $entry->delete();

        return ApiResponse::success(null, 'Time entry deleted successfully');
    }

    // -------------------------------------------------------------------------

    private function assertTaskBelongsToProject(ProjectTask $task, Project $project): void
    {
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found for this project.');
        }
    }

    private function assertEntryBelongsToTask(ProjectTimeEntry $entry, ProjectTask $task): void
    {
        if ($entry->task_id !== $task->id) {
            abort(404, 'Time entry not found for this task.');
        }
    }
}
