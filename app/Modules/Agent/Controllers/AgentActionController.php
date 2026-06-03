<?php

namespace App\Modules\Agent\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Agent\Models\AgentActionLog;
use App\Modules\Agent\Services\AgentActionService;
use App\Modules\Agent\Services\AgentCommandParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class AgentActionController extends Controller
{
    public function __construct(
        private readonly AgentActionService $agentActions,
        private readonly AgentCommandParserService $commandParser,
    ) {}

    public function parse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $parsed = $this->commandParser->parse($request->user(), $validated['command']);
        $preview = null;

        if (($parsed['clarificationNeeded'] ?? true) === false && ($parsed['action'] ?? 'unknown') !== 'unknown') {
            $actionData = $this->validateActionData([
                'action' => $parsed['action'],
                'payload' => $parsed['payload'] ?? [],
            ]);

            $preview = $this->agentActions->preview($request->user(), $actionData['action'], $actionData['payload']);
        }

        $log = AgentActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'agent.parse',
            'status' => 'parsed',
            'payload' => ['command' => $validated['command']],
            'preview' => $preview,
            'result' => $parsed,
        ]);

        return ApiResponse::success([
            'logId' => $log->id,
            'parsed' => $parsed,
            'preview' => $preview,
        ], 'Agent command parsed successfully');
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $this->validateAction($request);
        $preview = $this->agentActions->preview($request->user(), $validated['action'], $validated['payload']);

        $log = AgentActionLog::create([
            'user_id' => $request->user()->id,
            'action' => $validated['action'],
            'status' => 'previewed',
            'payload' => $validated['payload'],
            'preview' => $preview,
        ]);

        return ApiResponse::success([
            'logId' => $log->id,
            'preview' => $preview,
        ], 'Agent action preview generated successfully');
    }

    public function execute(Request $request): JsonResponse
    {
        $validated = $this->validateAction($request);

        try {
            $result = $this->agentActions->execute($request->user(), $validated['action'], $validated['payload']);

            $log = AgentActionLog::create([
                'user_id' => $request->user()->id,
                'action' => $validated['action'],
                'status' => 'executed',
                'payload' => $validated['payload'],
                'preview' => $result['preview'] ?? null,
                'result' => $result,
                'executed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            AgentActionLog::create([
                'user_id' => $request->user()->id,
                'action' => $validated['action'],
                'status' => 'failed',
                'payload' => $validated['payload'],
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return ApiResponse::success([
            'logId' => $log->id,
            'result' => $result,
        ], 'Agent action executed successfully');
    }

    public function logs(Request $request): JsonResponse
    {
        $logs = AgentActionLog::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (AgentActionLog $log): array => [
                'logId' => $log->id,
                'action' => $log->action,
                'status' => $log->status,
                'payload' => $log->payload,
                'preview' => $log->preview,
                'result' => $log->result,
                'error' => $log->error,
                'executedAt' => $log->executed_at?->toISOString(),
                'createdAt' => $log->created_at?->toISOString(),
            ]);

        return ApiResponse::success(['logs' => $logs], 'Agent action logs retrieved successfully');
    }

    /**
     * @return array{action:string, payload:array<string, mixed>}
     */
    private function validateAction(Request $request): array
    {
        return $this->validateActionData($request->all());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{action:string, payload:array<string, mixed>}
     */
    private function validateActionData(array $data): array
    {
        $validated = Validator::make($data, [
            'action' => ['required', 'string', Rule::in(['project.create_tasks', 'project.start_timer', 'reports.get'])],
            'payload' => ['required', 'array'],
        ])->validate();

        return match ($validated['action']) {
            'project.create_tasks' => Validator::make($data, [
                'action' => ['required', 'string'],
                'payload' => ['required', 'array'],
                'payload.projectId' => ['required', 'integer', 'exists:projects,id'],
                'payload.tasks' => ['required', 'array', 'min:1', 'max:20'],
                'payload.tasks.*.title' => ['required', 'string', 'max:255'],
                'payload.tasks.*.description' => ['nullable', 'string', 'max:5000'],
                'payload.tasks.*.assigneeId' => ['nullable', 'integer', 'exists:users,id'],
                'payload.tasks.*.dueDate' => ['nullable', 'date'],
                'payload.tasks.*.milestoneId' => ['nullable', 'integer', 'exists:milestones,id'],
                'payload.tasks.*.estimatedHours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
                'payload.tasks.*.deliverables' => ['nullable', 'array', 'max:20'],
                'payload.tasks.*.deliverables.*' => ['required', 'string', 'max:255'],
            ])->validate(),
            'project.start_timer' => Validator::make($data, [
                'action' => ['required', 'string'],
                'payload' => ['required', 'array'],
                'payload.projectId' => ['required', 'integer', 'exists:projects,id'],
                'payload.taskId' => ['required', 'integer', 'exists:project_tasks,id'],
            ])->validate(),
            'reports.get' => Validator::make($data, [
                'action' => ['required', 'string'],
                'payload' => ['required', 'array'],
                'payload.report' => ['required', 'string', Rule::in(['overview', 'projects', 'team-time', 'sales-finance'])],
                'payload.filters' => ['nullable', 'array'],
                'payload.filters.from' => ['nullable', 'date'],
                'payload.filters.to' => ['nullable', 'date'],
                'payload.filters.projectId' => ['nullable', 'integer', 'exists:projects,id'],
                'payload.filters.clientId' => ['nullable', 'integer', 'exists:clients,id'],
                'payload.filters.userId' => ['nullable', 'integer', 'exists:users,id'],
                'payload.filters.status' => ['nullable', 'string', 'max:64'],
            ])->validate(),
        };
    }
}
