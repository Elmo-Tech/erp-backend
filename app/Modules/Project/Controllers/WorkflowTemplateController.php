<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\WorkflowTemplate;
use App\Modules\Project\Services\ProjectWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;

class WorkflowTemplateController extends Controller
{
    public function __construct(
        private readonly ProjectWorkflowService $workflows,
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(): JsonResponse
    {
        $templates = WorkflowTemplate::query()
            ->with('stages')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'templates' => $templates->map($this->resource(...))->values(),
        ], 'Workflow templates retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeTemplateManagement($request);
        $validated = $request->validate($this->rules());
        $this->workflows->validateRequiredStatuses($validated['stages']);
        $template = $this->saveTemplate(new WorkflowTemplate, $validated, $request->user()->id);

        return ApiResponse::success($this->resource($template), 'Workflow template created successfully', 201);
    }

    public function update(Request $request, WorkflowTemplate $workflowTemplate): JsonResponse
    {
        $this->authorizeTemplateManagement($request);
        $validated = $request->validate($this->rules());
        $this->workflows->validateRequiredStatuses($validated['stages']);
        $template = $this->saveTemplate($workflowTemplate, $validated, $workflowTemplate->created_by);

        return ApiResponse::success($this->resource($template), 'Workflow template updated successfully');
    }

    public function destroy(Request $request, WorkflowTemplate $workflowTemplate): JsonResponse
    {
        $this->authorizeTemplateManagement($request);
        abort_if($workflowTemplate->is_default, 422, 'The default workflow template cannot be deleted.');
        $workflowTemplate->delete();

        return ApiResponse::success(null, 'Workflow template deleted successfully');
    }

    private function authorizeTemplateManagement(Request $request): void
    {
        abort_unless(
            $request->user()->can('manage_workflow_templates'),
            403,
            'Unauthorized'
        );
    }

    private function saveTemplate(
        WorkflowTemplate $template,
        array $validated,
        ?int $creatorId,
    ): WorkflowTemplate {
        return DB::transaction(function () use ($template, $validated, $creatorId): WorkflowTemplate {
            $template->fill([
                'name' => $validated['name'],
                'created_by' => $creatorId,
            ])->save();
            $template->stages()->delete();

            foreach ($validated['stages'] as $position => $stage) {
                $template->stages()->create([
                    'name' => $stage['name'],
                    'status' => $stage['status'],
                    'color' => $stage['color'],
                    'position' => $position,
                    'is_terminal' => $stage['isTerminal'] ?? false,
                ]);
            }

            return $template->load('stages');
        });
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'stages' => ['required', 'array', 'min:1', 'max:20'],
            'stages.*.name' => ['required', 'string', 'max:80'],
            'stages.*.status' => ['required', new Enum(ExecutionTaskStatus::class)],
            'stages.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'stages.*.isTerminal' => ['nullable', 'boolean'],
        ];
    }

    private function resource(WorkflowTemplate $template): array
    {
        $template->loadMissing('stages');

        return [
            'templateId' => $template->id,
            'name' => $template->name,
            'isDefault' => $template->is_default,
            'stages' => $template->stages->map(fn ($stage): array => [
                'stageId' => $stage->id,
                'name' => $stage->name,
                'status' => $stage->status->value,
                'color' => $stage->color,
                'position' => $stage->position,
                'isTerminal' => $stage->is_terminal,
            ])->values(),
        ];
    }
}
