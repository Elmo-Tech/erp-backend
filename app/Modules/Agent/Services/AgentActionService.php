<?php

namespace App\Modules\Agent\Services;

use App\Modules\Project\Actions\ToggleTaskTimerAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Resources\ProjectTaskResource;
use App\Modules\Project\Resources\ProjectTimeEntryResource;
use App\Modules\Project\Services\ProjectTaskRelationshipValidator;
use App\Modules\Reports\Services\ReportsService;
use App\Modules\Settings\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AgentActionService
{
    public function __construct(
        private readonly ToggleTaskTimerAction $toggleTimer,
        private readonly ReportsService $reports,
        private readonly ProjectTaskRelationshipValidator $relationshipValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function preview(User $user, string $action, array $payload): array
    {
        return match ($action) {
            'project.create_tasks' => $this->previewCreateTasks($user, $payload),
            'project.start_timer' => $this->previewStartTimer($user, $payload),
            'reports.get' => $this->previewReport($user, $payload),
            default => abort(422, 'Unsupported agent action.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function execute(User $user, string $action, array $payload): array
    {
        return match ($action) {
            'project.create_tasks' => $this->executeCreateTasks($user, $payload),
            'project.start_timer' => $this->executeStartTimer($user, $payload),
            'reports.get' => $this->executeReport($user, $payload),
            default => abort(422, 'Unsupported agent action.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function previewCreateTasks(User $user, array $payload): array
    {
        $project = Project::findOrFail((int) $payload['projectId']);

        Gate::forUser($user)->authorize('view', $project);
        Gate::forUser($user)->authorize('create', [ProjectTask::class, $project]);

        foreach ($payload['tasks'] as $task) {
            $this->relationshipValidator->validate($user, $project, $task);
        }

        if (collect($payload['tasks'])->contains(
            fn (array $task): bool => ! empty($task['deliverables'])
        )) {
            Gate::forUser($user)->authorize('createDeliverable', [ProjectTask::class, $project]);
        }

        $tasks = collect($payload['tasks'])
            ->map(fn (array $task): array => [
                'title' => $task['title'],
                'assigneeId' => $task['assigneeId'] ?? null,
                'dueDate' => $task['dueDate'] ?? null,
                'milestoneId' => $task['milestoneId'] ?? null,
                'estimatedHours' => $task['estimatedHours'] ?? null,
                'deliverables' => array_values($task['deliverables'] ?? []),
            ])
            ->values()
            ->all();

        return [
            'action' => 'project.create_tasks',
            'executable' => true,
            'project' => [
                'projectId' => $project->id,
                'title' => $project->title,
            ],
            'operations' => [
                [
                    'type' => 'create_project_tasks',
                    'count' => count($tasks),
                    'tasks' => $tasks,
                ],
            ],
            'requiredPermissions' => ['use_ai_agent', 'view_projects', 'create_tasks|manage_tasks|manage_all_projects'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function executeCreateTasks(User $user, array $payload): array
    {
        $preview = $this->previewCreateTasks($user, $payload);
        $project = Project::findOrFail((int) $payload['projectId']);

        $createdTasks = DB::transaction(function () use ($project, $payload) {
            return collect($payload['tasks'])
                ->map(function (array $taskPayload) use ($project): ProjectTask {
                    $task = $project->executionTasks()->create([
                        'milestone_id' => $taskPayload['milestoneId'] ?? null,
                        'title' => $taskPayload['title'],
                        'description' => $taskPayload['description'] ?? null,
                        'status' => 'to_do',
                        'estimated_hours' => $taskPayload['estimatedHours'] ?? null,
                        'due_date' => $taskPayload['dueDate'] ?? null,
                        'assignee_id' => $taskPayload['assigneeId'] ?? null,
                    ]);

                    foreach (($taskPayload['deliverables'] ?? []) as $deliverableTitle) {
                        $task->deliverables()->create([
                            'title' => $deliverableTitle,
                            'is_completed' => false,
                        ]);
                    }

                    return $task->load(['assignee', 'deliverables', 'milestone', 'project']);
                })
                ->values();
        });

        return [
            'preview' => $preview,
            'createdTasks' => ProjectTaskResource::collection($createdTasks)->resolve(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function previewStartTimer(User $user, array $payload): array
    {
        $project = Project::findOrFail((int) $payload['projectId']);
        $task = ProjectTask::with('project')->findOrFail((int) $payload['taskId']);

        abort_if($task->project_id !== $project->id, 404, 'Task not found for this project.');

        Gate::forUser($user)->authorize('view', $project);
        Gate::forUser($user)->authorize('execute', $task);
        abort_unless($this->canStartTimer($user), 403, 'Unauthorized');

        return [
            'action' => 'project.start_timer',
            'executable' => true,
            'project' => [
                'projectId' => $project->id,
                'title' => $project->title,
            ],
            'task' => [
                'taskId' => $task->id,
                'title' => $task->title,
                'status' => $task->status->value,
            ],
            'operations' => [
                [
                    'type' => 'toggle_project_timer',
                    'targetUserId' => $user->id,
                ],
            ],
            'requiredPermissions' => ['use_ai_agent', 'start_timer|log_time|manage_time_entries'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function executeStartTimer(User $user, array $payload): array
    {
        $preview = $this->previewStartTimer($user, $payload);
        $task = ProjectTask::findOrFail((int) $payload['taskId']);
        $entry = $this->toggleTimer->execute($task, $user);

        $entry->load(['user:id,name,avatar', 'task.project']);

        return [
            'preview' => $preview,
            'timeEntry' => (new ProjectTimeEntryResource($entry))->resolve(),
            'state' => $entry->end_time === null ? 'started' : 'stopped',
        ];
    }

    private function canStartTimer(User $user): bool
    {
        return $user->can('start_timer')
            || $user->can('log_time')
            || $user->can('manage_time_entries');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function previewReport(User $user, array $payload): array
    {
        $report = (string) $payload['report'];
        $this->reports->authorizeReport($user, $report);
        $requiredPermissions = ['use_ai_agent', 'view_reports'];

        if (in_array($report, ['overview', 'projects', 'sales-finance'], true)) {
            $requiredPermissions[] = 'view_financial_stats';
        }

        return [
            'action' => 'reports.get',
            'executable' => true,
            'report' => $payload['report'],
            'filters' => $payload['filters'] ?? [],
            'operations' => [
                [
                    'type' => 'read_report',
                    'report' => $payload['report'],
                ],
            ],
            'requiredPermissions' => $requiredPermissions,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function executeReport(User $user, array $payload): array
    {
        $preview = $this->previewReport($user, $payload);
        $request = Request::create('/api/v1/reports/'.$payload['report'], 'GET', $payload['filters'] ?? []);
        $request->setUserResolver(fn () => $user);

        $report = match ($payload['report']) {
            'projects' => $this->reports->projects($request),
            'team-time' => $this->reports->teamTime($request),
            'sales-finance' => $this->reports->salesFinance($request),
            default => $this->reports->overview($request),
        };

        return [
            'preview' => $preview,
            'report' => $report,
        ];
    }
}
