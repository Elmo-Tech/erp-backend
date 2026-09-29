<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\TaskSupervision\SetTaskSupervisorRequest;
use App\Http\Requests\V1\TaskSupervision\StartTaskSupervisionSessionRequest;
use App\Http\Requests\V1\TaskSupervision\StopTaskSupervisionSessionRequest;
use App\Http\Requests\V1\TaskSupervision\StoreTaskHelpRequest as StoreTaskHelpRequestRequest;
use App\Http\Requests\V1\TaskSupervision\TaskSupervisionSummaryRequest;
use App\Http\Requests\V1\TaskSupervision\UpdateTaskHelpRequest as UpdateTaskHelpRequestRequest;
use App\Http\Resources\V1\Tasks\TaskHelpRequestResource;
use App\Http\Resources\V1\Tasks\TaskResource;
use App\Http\Resources\V1\Tasks\TaskSupervisionSessionResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskHelpRequest;
use App\Models\TaskSupervisionSession;
use App\Models\User;
use App\Services\Projects\ProjectAccess;
use App\Services\Tasks\TaskSupervisionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class TaskSupervisionController extends Controller
{
    public function __construct(
        private TaskSupervisionService $taskSupervisionService,
        private ProjectAccess $projectAccess
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function setSupervisor(SetTaskSupervisorRequest $request, Task $task): JsonResponse
    {
        if (! $this->canManageTask($request->user(), $task)) {
            return ApiResponse::forbidden('You do not have permission to manage this task supervisor.');
        }

        try {
            $task = $this->taskSupervisionService->setSupervisor(
                $task,
                $request->validated('supervisorId'),
                $request->user()
            );

            return ApiResponse::success(
                new TaskResource($task),
                'Task supervisor updated successfully'
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }

    public function show(Task $task, Request $request): JsonResponse
    {
        if (! $this->canViewSupervision($request->user(), $task)) {
            return ApiResponse::forbidden('You do not have permission to view this task supervision.');
        }

        $task->load([
            'supervisor',
            'helpRequests.requester',
            'helpRequests.acknowledgedBy',
            'helpRequests.resolvedBy',
            'supervisionSessions.supervisor',
            'supervisionSessions.helpRequest',
        ]);

        return ApiResponse::success([
            'supervisor' => $this->userSummary($task->supervisor),
            'helpRequests' => TaskHelpRequestResource::collection($task->helpRequests),
            'sessions' => TaskSupervisionSessionResource::collection($task->supervisionSessions),
            'summary' => $this->taskSupervisionService->summary(
                $task->helpRequests,
                $task->supervisionSessions,
                $task->id
            ),
        ], 'Task supervision retrieved successfully');
    }

    public function storeHelpRequest(StoreTaskHelpRequestRequest $request, Task $task): JsonResponse
    {
        if (! $this->isTaskAssignee($request->user(), $task)) {
            return ApiResponse::forbidden('Only task assignees can request supervisor help.');
        }

        try {
            $helpRequest = $this->taskSupervisionService->createHelpRequest(
                $task,
                $request->user(),
                $request->validated('message')
            );

            return ApiResponse::success(
                new TaskHelpRequestResource($helpRequest),
                'Help request created successfully',
                201
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }

    public function updateHelpRequest(
        UpdateTaskHelpRequestRequest $request,
        TaskHelpRequest $helpRequest
    ): JsonResponse {
        $helpRequest->load('task');
        $task = $helpRequest->task;
        $actor = $request->user();
        $status = $request->validated('status');

        $isManager = $this->canManageTask($actor, $task);
        $isSupervisor = $this->isSupervisor($actor, $task);
        $isRequester = $helpRequest->requested_by === $actor->id && $this->isTaskAssignee($actor, $task);

        $canTransition = $isManager
            || ($isSupervisor && in_array($status, [TaskHelpRequest::STATUS_ACKNOWLEDGED, TaskHelpRequest::STATUS_RESOLVED], true))
            || ($isRequester && $status === TaskHelpRequest::STATUS_CANCELLED);

        if (! $canTransition) {
            return ApiResponse::forbidden('You do not have permission to update this help request.');
        }

        try {
            $helpRequest = $this->taskSupervisionService->transitionHelpRequest(
                $helpRequest,
                $actor,
                $status,
                $request->validated('resolutionNote')
            );

            return ApiResponse::success(
                new TaskHelpRequestResource($helpRequest),
                'Help request updated successfully'
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }

    public function startSession(StartTaskSupervisionSessionRequest $request, Task $task): JsonResponse
    {
        if (! $this->isSupervisor($request->user(), $task)) {
            return ApiResponse::forbidden('Only the assigned supervisor can start a supervision session.');
        }

        try {
            $session = $this->taskSupervisionService->startSession(
                $task,
                $request->user(),
                $request->validated('helpRequestId'),
                $request->validated('notes')
            );

            return ApiResponse::success(
                new TaskSupervisionSessionResource($session),
                'Supervision session started successfully',
                201
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }
    }

    public function heartbeat(TaskSupervisionSession $session, Request $request): JsonResponse
    {
        $session->load('task');
        if ($session->supervisor_id !== $request->user()->id || ! $this->isSupervisor($request->user(), $session->task)) {
            return ApiResponse::forbidden('Only the assigned supervisor can update this supervision session.');
        }

        try {
            $session = $this->taskSupervisionService->heartbeat($session, $request->user());

            return ApiResponse::success(
                new TaskSupervisionSessionResource($session),
                'Supervision session heartbeat recorded successfully'
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }
    }

    public function stopSession(
        StopTaskSupervisionSessionRequest $request,
        TaskSupervisionSession $session
    ): JsonResponse {
        $session->load('task');
        if ($session->supervisor_id !== $request->user()->id || ! $this->isSupervisor($request->user(), $session->task)) {
            return ApiResponse::forbidden('Only the assigned supervisor can stop this supervision session.');
        }

        try {
            $session = $this->taskSupervisionService->stopSession(
                $session,
                $request->user(),
                $request->validated('stopReason')
            );

            return ApiResponse::success(
                new TaskSupervisionSessionResource($session),
                'Supervision session stopped successfully'
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }
    }

    public function active(Request $request): JsonResponse
    {
        $session = $this->taskSupervisionService->activeSession($request->user());

        return ApiResponse::success(
            $session === null ? null : new TaskSupervisionSessionResource($session),
            'Active supervision session retrieved successfully'
        );
    }

    public function summary(TaskSupervisionSummaryRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $task = null;

        if (isset($filters['taskId'])) {
            $task = Task::query()->findOrFail($filters['taskId']);
            if (! $this->canViewSupervision($request->user(), $task)) {
                return ApiResponse::forbidden('You do not have permission to view this task supervision summary.');
            }
        } elseif (! $this->isGlobalManager($request->user())) {
            return ApiResponse::forbidden('A taskId is required unless you can manage task teams.');
        }

        $sessions = $this->filteredSessions($filters, $task, $request->user())->get();
        $helpRequests = $this->filteredHelpRequests($filters, $task, $request->user())->get();

        return ApiResponse::success([
            'summary' => $this->taskSupervisionService->summary($helpRequests, $sessions, $task?->id),
        ], 'Task supervision summary retrieved successfully');
    }

    private function canManageTask(User $user, Task $task): bool
    {
        if (! $user->can('manageTeam', $task)) {
            return false;
        }

        if ($task->project_id === null || $user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        $project = Project::withoutGlobalScopes()->find($task->project_id);

        return $project !== null && $this->projectAccess->canView($user, $project);
    }

    private function canViewSupervision(User $user, Task $task): bool
    {
        return $this->canManageTask($user, $task)
            || $this->isSupervisor($user, $task)
            || $this->isTaskAssignee($user, $task);
    }

    private function isSupervisor(User $user, Task $task): bool
    {
        return $task->supervisor_id === $user->id;
    }

    private function isTaskAssignee(User $user, Task $task): bool
    {
        return $task->isAssignedTo($user);
    }

    private function isGlobalManager(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->can('manage_task_team');
    }

    private function filteredSessions(array $filters, ?Task $task, User $user): Builder
    {
        $query = TaskSupervisionSession::query()->with(['supervisor', 'helpRequest']);

        if ($task !== null) {
            $query->where('task_id', $task->id);
        } else {
            $this->limitToManagedProjects($query, $user);
        }

        if (isset($filters['supervisorId'])) {
            $query->where('supervisor_id', $filters['supervisorId']);
        }

        if (isset($filters['requestedBy'])) {
            $query->whereHas('helpRequest', fn (Builder $query) => $query->where('requested_by', $filters['requestedBy']));
        }

        if (isset($filters['from'])) {
            $query->whereDate('started_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->whereDate('started_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('started_at');
    }

    private function filteredHelpRequests(array $filters, ?Task $task, User $user): Builder
    {
        $query = TaskHelpRequest::query()->with(['requester', 'acknowledgedBy', 'resolvedBy']);

        if ($task !== null) {
            $query->where('task_id', $task->id);
        } else {
            $this->limitToManagedProjects($query, $user);
        }

        if (isset($filters['supervisorId'])) {
            $query->whereHas('task', fn (Builder $query) => $query->where('supervisor_id', $filters['supervisorId']));
        }

        if (isset($filters['requestedBy'])) {
            $query->where('requested_by', $filters['requestedBy']);
        }

        if (isset($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('created_at');
    }

    private function limitToManagedProjects(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(['admin', 'super-admin']) || $user->can('view_all_projects')) {
            return;
        }

        $query->whereHas('task.project.members', fn (Builder $query) => $query->where('user_id', $user->id));
    }

    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'userId' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
        ];
    }
}
