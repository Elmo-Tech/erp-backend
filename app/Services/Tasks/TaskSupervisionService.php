<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskHelpRequest;
use App\Models\TaskSupervisionSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class TaskSupervisionService
{
    /** @param list<int> $supervisorIds */
    public function setSupervisors(Task $task, array $supervisorIds, User $actor): Task
    {
        return DB::transaction(function () use ($task, $supervisorIds, $actor): Task {
            $task = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $previousIds = array_values(array_filter([$task->supervisor_id, $task->secondary_supervisor_id]));
            if (count($supervisorIds) > 2 || count(array_unique($supervisorIds)) !== count($supervisorIds)) {
                throw new InvalidArgumentException('Choose up to two different supervisors.');
            }
            foreach ($supervisorIds as $id) {
                $candidate = User::query()->whereKey($id)->where('is_active', true)->first();
                if ($candidate === null) throw new InvalidArgumentException('The selected supervisor must be an active user.');
                $isMember = $task->project_id !== null && $task->project()->withoutGlobalScopes()->first()?->members()->where('user_id', $id)->exists();
                if (! $isMember && ! $task->assignees()->where('user_id', $id)->exists()) {
                    throw new InvalidArgumentException('The selected supervisor must be an active project member or task assignee.');
                }
            }
            $removedIds = array_diff($previousIds, $supervisorIds);
            if ($removedIds && TaskSupervisionSession::where('task_id', $task->id)->whereIn('supervisor_id', $removedIds)->whereNull('ended_at')->exists()) {
                throw new InvalidArgumentException('Stop the supervisor’s active session before removing them.');
            }
            $task->update(['supervisor_id' => $supervisorIds[0] ?? null, 'secondary_supervisor_id' => $supervisorIds[1] ?? null, 'updated_by' => $actor->id]);
            if ($previousIds !== $supervisorIds) {
                $this->logActivity($task, $actor, TaskActivity::TYPE_SUPERVISOR_CHANGED, $supervisorIds ? 'Changed task supervisor' : 'Removed task supervisor', [
                    'previous_supervisor_ids' => $previousIds, 'supervisor_ids' => $supervisorIds,
                ]);
            }
            return $task->fresh()->load(['supervisor', 'secondarySupervisor']);
        });
    }

    public function setSupervisor(Task $task, ?int $supervisorId, User $actor): Task
    {
        return $this->setSupervisors($task, array_values(array_filter([$supervisorId])), $actor);
    }

    public function createHelpRequest(Task $task, User $requester, string $message): TaskHelpRequest
    {
        return DB::transaction(function () use ($task, $requester, $message): TaskHelpRequest {
            $task = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($task->supervisor_id === null && $task->secondary_supervisor_id === null) {
                throw new InvalidArgumentException('A supervisor must be assigned before requesting help.');
            }

            $hasActiveRequest = TaskHelpRequest::query()
                ->where('task_id', $task->id)
                ->where('requested_by', $requester->id)
                ->whereIn('status', [TaskHelpRequest::STATUS_OPEN, TaskHelpRequest::STATUS_ACKNOWLEDGED])
                ->exists();

            if ($hasActiveRequest) {
                throw new InvalidArgumentException('You already have an active help request for this task.');
            }

            $helpRequest = TaskHelpRequest::create([
                'task_id' => $task->id,
                'requested_by' => $requester->id,
                'message' => $message,
                'status' => TaskHelpRequest::STATUS_OPEN,
            ]);

            $this->logActivity(
                $task,
                $requester,
                TaskActivity::TYPE_HELP_REQUESTED,
                'Requested supervisor help',
                ['help_request_id' => $helpRequest->id]
            );

            return $helpRequest->load('requester');
        });
    }

    public function transitionHelpRequest(
        TaskHelpRequest $helpRequest,
        User $actor,
        string $status,
        ?string $resolutionNote = null
    ): TaskHelpRequest {
        return DB::transaction(function () use ($helpRequest, $actor, $status, $resolutionNote): TaskHelpRequest {
            $helpRequest = TaskHelpRequest::query()
                ->whereKey($helpRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            $now = now();

            match ($status) {
                TaskHelpRequest::STATUS_ACKNOWLEDGED => $this->acknowledgeHelpRequest($helpRequest, $actor, $now),
                TaskHelpRequest::STATUS_RESOLVED => $this->resolveHelpRequest($helpRequest, $actor, $now, $resolutionNote),
                TaskHelpRequest::STATUS_CANCELLED => $this->cancelHelpRequest($helpRequest),
                default => throw new InvalidArgumentException('Unsupported help request status.'),
            };

            $task = $helpRequest->task;
            $this->logActivity(
                $task,
                $actor,
                TaskActivity::TYPE_HELP_REQUEST_UPDATED,
                'Updated help request to '.$status,
                ['help_request_id' => $helpRequest->id, 'status' => $status]
            );

            return $helpRequest->fresh()->load(['requester', 'acknowledgedBy', 'resolvedBy']);
        });
    }

    public function startSession(
        Task $task,
        User $supervisor,
        ?int $helpRequestId = null,
        ?string $notes = null
    ): TaskSupervisionSession {
        return DB::transaction(function () use ($task, $supervisor, $helpRequestId, $notes): TaskSupervisionSession {
            User::query()->whereKey($supervisor->id)->lockForUpdate()->firstOrFail();
            $task = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if (! $task->isSupervisor($supervisor)) {
                throw new RuntimeException('Only the assigned supervisor can start a supervision session.');
            }

            if (TaskSupervisionSession::query()
                ->where('supervisor_id', $supervisor->id)
                ->whereNull('ended_at')
                ->exists()) {
                throw new RuntimeException('You already have an active supervision session.');
            }

            $helpRequest = null;
            if ($helpRequestId !== null) {
                $helpRequest = TaskHelpRequest::query()
                    ->whereKey($helpRequestId)
                    ->where('task_id', $task->id)
                    ->lockForUpdate()
                    ->first();

                if ($helpRequest === null || ! $helpRequest->isActive()) {
                    throw new InvalidArgumentException('The help request is not active for this task.');
                }

                if ($helpRequest->status === TaskHelpRequest::STATUS_OPEN) {
                    $this->acknowledgeHelpRequest($helpRequest, $supervisor, now());
                }
            }

            $now = now();
            $session = TaskSupervisionSession::create([
                'task_id' => $task->id,
                'supervisor_id' => $supervisor->id,
                'help_request_id' => $helpRequest?->id,
                'session_id' => (string) Str::uuid(),
                'notes' => $notes,
                'started_at' => $now,
                'last_heartbeat_at' => $now,
            ]);

            $this->logActivity(
                $task,
                $supervisor,
                TaskActivity::TYPE_SUPERVISION_SESSION_STARTED,
                'Started supervision session',
                [
                    'supervision_session_id' => $session->id,
                    'help_request_id' => $helpRequest?->id,
                    'notes' => $notes,
                ]
            );

            return $session->load(['supervisor', 'helpRequest']);
        });
    }

    public function heartbeat(TaskSupervisionSession $session, User $supervisor): TaskSupervisionSession
    {
        return DB::transaction(function () use ($session, $supervisor): TaskSupervisionSession {
            $session = TaskSupervisionSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->supervisor_id !== $supervisor->id || ! $session->isActive()) {
                throw new RuntimeException('The supervision session is no longer active.');
            }

            $session->update(['last_heartbeat_at' => now()]);

            return $session->fresh()->load(['supervisor', 'helpRequest']);
        });
    }

    public function stopSession(
        TaskSupervisionSession $session,
        User $supervisor,
        ?string $stopReason = null
    ): TaskSupervisionSession {
        return DB::transaction(function () use ($session, $supervisor, $stopReason): TaskSupervisionSession {
            $session = TaskSupervisionSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->supervisor_id !== $supervisor->id || ! $session->isActive()) {
                throw new RuntimeException('The supervision session is no longer active.');
            }

            $endedAt = now();
            $durationMinutes = $session->started_at === null
                ? 0
                : max(0, (int) floor($session->started_at->diffInSeconds($endedAt, false) / 60));

            $session->update([
                'ended_at' => $endedAt,
                'last_heartbeat_at' => $endedAt,
                'duration_minutes' => $durationMinutes,
                'stop_reason' => $stopReason,
            ]);

            $this->logActivity(
                $session->task,
                $supervisor,
                TaskActivity::TYPE_SUPERVISION_SESSION_STOPPED,
                'Stopped supervision session',
                [
                    'supervision_session_id' => $session->id,
                    'duration_minutes' => $durationMinutes,
                    'stop_reason' => $stopReason,
                ]
            );

            return $session->fresh()->load(['supervisor', 'helpRequest']);
        });
    }

    public function activeSession(User $supervisor): ?TaskSupervisionSession
    {
        return TaskSupervisionSession::query()
            ->where('supervisor_id', $supervisor->id)
            ->whereNull('ended_at')
            ->with(['task.supervisor', 'helpRequest', 'supervisor'])
            ->latest('started_at')
            ->first();
    }

    /**
     * @param  Collection<int, TaskHelpRequest>  $helpRequests
     * @param  Collection<int, TaskSupervisionSession>  $sessions
     * @return array<string, int|null>
     */
    public function summary(Collection $helpRequests, Collection $sessions, ?int $taskId = null): array
    {
        $helpRequestCounts = $helpRequests->countBy('status');

        return [
            'taskId' => $taskId,
            'totalHelpRequests' => $helpRequests->count(),
            'openHelpRequests' => (int) ($helpRequestCounts[TaskHelpRequest::STATUS_OPEN] ?? 0),
            'acknowledgedHelpRequests' => (int) ($helpRequestCounts[TaskHelpRequest::STATUS_ACKNOWLEDGED] ?? 0),
            'resolvedHelpRequests' => (int) ($helpRequestCounts[TaskHelpRequest::STATUS_RESOLVED] ?? 0),
            'cancelledHelpRequests' => (int) ($helpRequestCounts[TaskHelpRequest::STATUS_CANCELLED] ?? 0),
            'totalSessions' => $sessions->count(),
            'activeSessions' => $sessions->filter->isActive()->count(),
            'totalDurationMinutes' => (int) $sessions->sum(
                fn (TaskSupervisionSession $session) => $session->currentDurationMinutes() ?? 0
            ),
        ];
    }

    private function acknowledgeHelpRequest(TaskHelpRequest $helpRequest, User $actor, Carbon $acknowledgedAt): void
    {
        if ($helpRequest->status !== TaskHelpRequest::STATUS_OPEN) {
            throw new InvalidArgumentException('Only open help requests can be acknowledged.');
        }

        $helpRequest->update([
            'status' => TaskHelpRequest::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => $actor->id,
            'acknowledged_at' => $acknowledgedAt,
        ]);
    }

    private function resolveHelpRequest(
        TaskHelpRequest $helpRequest,
        User $actor,
        Carbon $resolvedAt,
        ?string $resolutionNote
    ): void {
        if ($helpRequest->status !== TaskHelpRequest::STATUS_ACKNOWLEDGED) {
            throw new InvalidArgumentException('Only acknowledged help requests can be resolved.');
        }

        $helpRequest->update([
            'status' => TaskHelpRequest::STATUS_RESOLVED,
            'resolved_by' => $actor->id,
            'resolved_at' => $resolvedAt,
            'resolution_note' => $resolutionNote,
        ]);
    }

    private function cancelHelpRequest(TaskHelpRequest $helpRequest): void
    {
        if (! $helpRequest->isActive()) {
            throw new InvalidArgumentException('Only active help requests can be cancelled.');
        }

        $helpRequest->update(['status' => TaskHelpRequest::STATUS_CANCELLED]);
    }

    private function logActivity(Task $task, User $actor, string $type, string $description, array $metadata): void
    {
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
