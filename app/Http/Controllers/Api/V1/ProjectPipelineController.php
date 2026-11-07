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
use Illuminate\Support\Facades\Storage;
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
                'max:25600',
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

    public function storeTaskReferences(Request $request, Project $project, Task $task): JsonResponse
    {
        abort_unless($task->is_pipeline_task && $task->project_id === $project->id, 404);
        abort_unless($this->access->canManagePipelineTaskCover($request->user(), $project, $task), 403);
        $request->validate(['images' => ['required', 'array', 'min:1', 'max:10'], 'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:25600', 'dimensions:max_width=6000,max_height=6000']]);
        $paths = [];
        try {
            DB::transaction(function () use ($task, $project, $request, &$paths): void {
                $locked = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
                $existing = $locked->reference_images ?? [];
                if (count($existing) + count($request->file('images')) > 10) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['images' => 'A task can have at most 10 reference images.']);
                }
                foreach ($request->file('images') as $image) {
                    $paths[] = $image->store("projects/{$project->id}/tasks/{$task->id}/references", 'public');
                }
                $locked->update(['reference_images' => [...$existing, ...$paths]]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }
        return ApiResponse::success(null, 'Reference images uploaded successfully');
    }

    public function destroyTaskReference(Request $request, Project $project, Task $task, string $reference): JsonResponse
    {
        abort_unless($task->is_pipeline_task && $task->project_id === $project->id, 404);
        abort_unless($this->access->canManagePipelineTaskCover($request->user(), $project, $task), 403);
        DB::transaction(function () use ($task, $reference): void {
            $locked = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $paths = $locked->reference_images ?? [];
            $path = collect($paths)->first(fn ($item) => basename($item) === $reference);
            abort_unless($path, 404);
            $locked->update(['reference_images' => array_values(array_filter($paths, fn ($item) => $item !== $path))]);
            DB::afterCommit(fn () => Storage::disk('public')->delete($path));
        });
        return ApiResponse::success(null, 'Reference image deleted successfully');
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

    public function globalSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage_workflow_templates'), 403);
        return ApiResponse::success(app(\App\Services\Projects\GlobalWorkflowService::class)->snapshot());
    }

    public function updateGlobalSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage_workflow_templates'), 403);
        $data = $request->validate([
            'revision' => ['required', 'string', 'size:64'],
            'stages' => ['required', 'array', 'min:2', 'max:20'],
            'stages.*.name' => ['required', 'string', 'max:80'],
            'stages.*.slug' => ['required', 'alpha_dash', 'max:80', 'distinct'],
            'stages.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'stages.*.category' => ['required', Rule::in(self::STAGE_CATEGORIES)],
            'stages.*.isTerminal' => ['required', 'boolean'],
            'mapping' => ['present', 'array'],
            'mapping.*' => ['required', 'string', 'max:80'],
        ]);
        app(\App\Services\Projects\GlobalWorkflowService::class)->apply($data, $request->user());
        return ApiResponse::success(app(\App\Services\Projects\GlobalWorkflowService::class)->snapshot(), 'Global workflow updated');
    }

    public function updateStages(Request $request, Project $project): JsonResponse
    {
        abort(403, 'Manage workflow centrally in settings.');
    }

    public function applyTemplate(Request $request, Project $project, WorkflowTemplate $template): JsonResponse
    {
        abort(403, 'Manage workflow centrally in settings.');
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
        abort(403, 'Manage workflow centrally in settings.');
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
