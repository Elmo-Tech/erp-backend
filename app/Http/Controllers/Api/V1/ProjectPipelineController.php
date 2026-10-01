<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectWorkflowStage;
use App\Models\Task;
use App\Models\WorkflowTemplate;
use App\Services\Projects\ProjectAccess;
use App\Services\Projects\ProjectWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectPipelineController extends Controller
{
    private const STAGE_CATEGORIES = [
        'backlog',
        'planned',
        'active',
        'paused',
        'review',
        'done',
        'cancelled',
    ];

    public function __construct(
        private ProjectWorkflowService $workflow,
        private ProjectAccess $access
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canViewTasks($request->user(), $project), 403);

        return ApiResponse::success(
            $this->workflow->getPipeline($project),
            'Project pipeline retrieved successfully'
        );
    }

    public function storeTask(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canCreatePipelineTasks($request->user(), $project), 403);

        $data = $request->validate([
            'workflowStageId' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'assignedUserIds' => ['nullable', 'array', 'max:20'],
            'assignedUserIds.*' => ['integer', 'distinct', 'exists:users,id'],
            'supervisorId' => ['nullable', 'integer', 'exists:users,id'],
            'dueDate' => ['nullable', 'date'],
            'estimatedHours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
        ]);

        $hasAssignees = filled($data['assignedTo'] ?? null)
            || ! empty($data['assignedUserIds'] ?? []);
        abort_if(
            $hasAssignees && ! $this->access->canAssignPipelineTasks($request->user(), $project),
            403
        );

        abort_if(
            isset($data['supervisorId']) && ! $this->canAssignSupervisor($request),
            403
        );

        $task = $this->workflow->createTask($project, $request->user(), $data);

        return ApiResponse::success(
            $this->workflow->taskPayload($task),
            'Pipeline task created successfully',
            201
        );
    }

    public function moveTask(
        Request $request,
        Project $project,
        Task $task
    ): JsonResponse {
        abort_unless($this->access->canMovePipelineTasks($request->user(), $project), 403);

        $data = $request->validate([
            'workflowStageId' => ['required', 'integer'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $stage = ProjectWorkflowStage::query()->findOrFail($data['workflowStageId']);
        $task = $this->workflow->moveTask(
            $project,
            $task,
            $stage,
            $request->user(),
            $data['position'] ?? null
        );

        return ApiResponse::success(
            $this->workflow->taskPayload($task),
            'Pipeline task moved successfully'
        );
    }

    public function updateTask(
        Request $request,
        Project $project,
        Task $task
    ): JsonResponse {
        abort_unless($this->access->canMovePipelineTasks($request->user(), $project), 403);

        $data = $request->validate([
            'workflowStageId' => ['sometimes', 'required', 'integer'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'assignedTo' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'assignedUserIds' => ['sometimes', 'nullable', 'array', 'max:20'],
            'assignedUserIds.*' => ['integer', 'distinct', 'exists:users,id'],
            'supervisorId' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'dueDate' => ['sometimes', 'nullable', 'date'],
            'estimatedHours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'urgent'])],
        ]);

        $changesAssignees = array_key_exists('assignedTo', $data)
            || array_key_exists('assignedUserIds', $data);
        abort_if(
            $changesAssignees && ! $this->access->canAssignPipelineTasks($request->user(), $project),
            403
        );

        abort_if(
            array_key_exists('supervisorId', $data) && ! $request->user()->can('manageTeam', $task),
            403
        );

        $task = $this->workflow->updateTask($project, $task, $request->user(), $data);

        return ApiResponse::success(
            $this->workflow->taskPayload($task),
            'Pipeline task updated successfully'
        );
    }

    public function destroyTask(
        Request $request,
        Project $project,
        Task $task
    ): JsonResponse {
        abort_unless($this->access->canDeletePipelineTasks($request->user(), $project), 403);

        $this->workflow->deleteTask($project, $task);

        return ApiResponse::success(null, 'Pipeline task deleted successfully');
    }

    public function storeTaskCover(
        Request $request,
        Project $project,
        Task $task
    ): JsonResponse {
        abort_unless($task->is_pipeline_task && $task->project_id === $project->id, 404);
        abort_unless($this->access->canManagePipelineTaskCover($request->user(), $project, $task), 403);

        $request->validate([
            'coverImage' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
                'dimensions:max_width=6000,max_height=6000',
            ],
        ]);

        $coverImagePath = $request->file('coverImage')
            ->store("projects/{$project->id}/tasks/{$task->id}/covers", 'public');
        $task = $this->workflow->replaceTaskCover($project, $task, $coverImagePath);

        return ApiResponse::success(
            $this->workflow->taskPayload($task),
            'Task cover image updated successfully'
        );
    }

    public function destroyTaskCover(
        Request $request,
        Project $project,
        Task $task
    ): JsonResponse {
        abort_unless($task->is_pipeline_task && $task->project_id === $project->id, 404);
        abort_unless($this->access->canManagePipelineTaskCover($request->user(), $project, $task), 403);

        $task = $this->workflow->removeTaskCover($project, $task);

        return ApiResponse::success(
            $this->workflow->taskPayload($task),
            'Task cover image removed successfully'
        );
    }

    public function updateStages(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canManageWorkflow($request->user(), $project), 403);

        $data = $request->validate([
            'stages' => ['required', 'array', 'min:2', 'max:20'],
            'stages.*.stageId' => ['nullable', 'integer'],
            'stages.*.name' => ['required', 'string', 'max:80'],
            'stages.*.slug' => ['required', 'alpha_dash', 'max:80', 'distinct'],
            'stages.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'stages.*.category' => ['required', Rule::in(self::STAGE_CATEGORIES)],
            'stages.*.isTerminal' => ['sometimes', 'boolean'],
        ]);

        $stages = $this->workflow->replaceProjectStages($project, $data['stages']);

        return ApiResponse::success(
            ['stages' => $stages->map(fn ($stage) => $this->workflow->stagePayload($stage))->all()],
            'Project workflow updated successfully'
        );
    }

    public function applyTemplate(
        Request $request,
        Project $project,
        WorkflowTemplate $template
    ): JsonResponse {
        abort_unless($this->access->canManageWorkflow($request->user(), $project), 403);
        abort_unless($template->is_active, 422);

        $stages = $this->workflow->applyTemplate($project, $template);

        return ApiResponse::success(
            ['stages' => $stages->map(fn ($stage) => $this->workflow->stagePayload($stage))->all()],
            'Workflow template applied successfully'
        );
    }

    public function templates(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('manage_workflow_templates')
                || $request->user()->can('manage_project_workflows'),
            403
        );

        $templates = WorkflowTemplate::query()
            ->where('is_active', true)
            ->with('stages')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (WorkflowTemplate $template) => $this->templatePayload($template));

        return ApiResponse::success(['templates' => $templates]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage_workflow_templates'), 403);

        $data = $this->validateTemplate($request);
        $template = DB::transaction(function () use ($data): WorkflowTemplate {
            if ($data['isDefault'] ?? false) {
                WorkflowTemplate::query()->update(['is_default' => false]);
            }

            $template = WorkflowTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => $data['isDefault'] ?? false,
                'is_active' => true,
            ]);

            foreach ($data['stages'] as $position => $stage) {
                $template->stages()->create([
                    'name' => $stage['name'],
                    'slug' => $stage['slug'],
                    'color' => $stage['color'],
                    'category' => $stage['category'],
                    'position' => $position,
                    'is_terminal' => $stage['isTerminal'] ?? false,
                ]);
            }

            return $template->load('stages');
        });

        return ApiResponse::success(
            $this->templatePayload($template),
            'Workflow template created successfully',
            201
        );
    }

    private function canAssignSupervisor(Request $request): bool
    {
        return ! $request->user()->hasRole('client')
            && ($request->user()->hasAnyRole(['admin', 'super-admin'])
                || $request->user()->can('manage_task_team'));
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'isDefault' => ['sometimes', 'boolean'],
            'stages' => ['required', 'array', 'min:2', 'max:20'],
            'stages.*.name' => ['required', 'string', 'max:80'],
            'stages.*.slug' => ['required', 'alpha_dash', 'max:80', 'distinct'],
            'stages.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'stages.*.category' => ['required', Rule::in(self::STAGE_CATEGORIES)],
            'stages.*.isTerminal' => ['sometimes', 'boolean'],
        ]);
    }

    private function templatePayload(WorkflowTemplate $template): array
    {
        return [
            'templateId' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'isDefault' => $template->is_default,
            'stages' => $template->stages
                ->map(fn ($stage) => [
                    'templateStageId' => $stage->id,
                    'name' => $stage->name,
                    'slug' => $stage->slug,
                    'color' => $stage->color,
                    'category' => $stage->category,
                    'position' => $stage->position,
                    'isTerminal' => $stage->is_terminal,
                ])
                ->all(),
        ];
    }
}
