<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectWorkflowStage;
use App\Modules\Project\Models\WorkflowTemplate;
use App\Modules\Project\Resources\ProjectTaskResource;
use App\Modules\Project\Services\ProjectWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class ProjectWorkflowController extends Controller
{
    public function __construct(
        private readonly ProjectWorkflowService $workflows,
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $this->authorize('viewAny', [ProjectTask::class, $project]);

        $stages = $this->workflows->ensureProjectWorkflow($project);

        return ApiResponse::success([
            'stages' => $stages->map($this->stageResource(...))->values(),
            'allowedActions' => [
                'manage' => $this->canManageProjectWorkflow(request()->user(), $project),
                'manageTemplates' => request()->user()->can('manage_workflow_templates'),
            ],
        ], 'Project workflow retrieved successfully');
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeWorkflowManagement($request, $project);
        $validated = $request->validate($this->workflowRules());
        $stages = $this->workflows->replaceProjectStages($project, $validated['stages']);

        return ApiResponse::success([
            'stages' => $stages->map($this->stageResource(...))->values(),
        ], 'Project workflow updated successfully');
    }

    public function applyTemplate(Request $request, Project $project): JsonResponse
    {
        $this->authorizeWorkflowManagement($request, $project);
        $validated = $request->validate([
            'templateId' => ['required', 'integer', 'exists:workflow_templates,id'],
        ]);
        $template = WorkflowTemplate::findOrFail($validated['templateId']);
        $stages = $this->workflows->applyTemplate($project, $template);

        return ApiResponse::success([
            'stages' => $stages->map($this->stageResource(...))->values(),
        ], 'Workflow template applied successfully');
    }

    public function moveTask(
        Request $request,
        Project $project,
        ProjectTask $task,
    ): JsonResponse {
        $this->authorize('view', $project);
        abort_unless($task->project_id === $project->id, 404);

        $validated = $request->validate([
            'workflowStageId' => ['required', 'integer', 'exists:project_workflow_stages,id'],
            'position' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $stage = ProjectWorkflowStage::findOrFail($validated['workflowStageId']);
        abort_unless($stage->project_id === $project->id, 422, 'The selected stage does not belong to this project.');

        $this->authorizeTransition($request, $task, $stage);
        $task = $this->workflows->moveTask($task, $stage, $validated['position'] ?? 0);
        $task->load($this->taskRelations());

        return ApiResponse::success(
            new ProjectTaskResource($task),
            'Task moved successfully'
        );
    }

    private function authorizeWorkflowManagement(Request $request, Project $project): void
    {
        $this->authorize('view', $project);
        abort_unless(
            $this->canManageProjectWorkflow($request->user(), $project),
            403,
            'Unauthorized'
        );
    }

    private function canManageProjectWorkflow($user, Project $project): bool
    {
        if ($user->can('manage_all_projects')) {
            return true;
        }

        return $user->can('manage_project_workflows')
            && $project->members()->where('user_id', $user->id)->exists();
    }

    private function authorizeTransition(
        Request $request,
        ProjectTask $task,
        ProjectWorkflowStage $stage,
    ): void {
        if ($stage->status === ExecutionTaskStatus::DONE) {
            $this->authorize('complete', $task);

            return;
        }

        if ($task->status === ExecutionTaskStatus::DONE) {
            $this->authorize('reopen', $task);

            return;
        }

        $this->authorize('changeStatus', [$task, $stage->status->value]);
    }

    private function workflowRules(): array
    {
        return [
            'stages' => ['required', 'array', 'min:1', 'max:20'],
            'stages.*.id' => ['nullable', 'integer'],
            'stages.*.name' => ['required', 'string', 'max:80'],
            'stages.*.status' => ['required', new Enum(ExecutionTaskStatus::class)],
            'stages.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'stages.*.isTerminal' => ['nullable', 'boolean'],
        ];
    }

    private function stageResource(ProjectWorkflowStage $stage): array
    {
        return [
            'stageId' => $stage->id,
            'name' => $stage->name,
            'status' => $stage->status->value,
            'color' => $stage->color,
            'position' => $stage->position,
            'isTerminal' => $stage->is_terminal,
            'taskCount' => $stage->tasks()->count(),
        ];
    }

    private function taskRelations(): array
    {
        return [
            'assignee',
            'assignees',
            'workflowStage',
            'deliverables',
            'milestone',
            'timeEntries.user',
        ];
    }
}
