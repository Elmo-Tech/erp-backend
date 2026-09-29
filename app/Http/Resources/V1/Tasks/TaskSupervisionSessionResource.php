<?php

namespace App\Http\Resources\V1\Tasks;

use App\Models\TaskSupervisionSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskSupervisionSession */
class TaskSupervisionSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'supervisionSessionId' => $this->id,
            'sessionId' => $this->session_id,
            'taskId' => $this->task_id,
            'supervisorId' => $this->supervisor_id,
            'supervisor' => $this->whenLoaded('supervisor', fn () => $this->userSummary($this->supervisor)),
            'helpRequestId' => $this->help_request_id,
            'notes' => $this->notes,
            'startedAt' => $this->started_at?->toISOString(),
            'lastHeartbeatAt' => $this->last_heartbeat_at?->toISOString(),
            'endedAt' => $this->ended_at?->toISOString(),
            'durationMinutes' => $this->currentDurationMinutes(),
            'stopReason' => $this->stop_reason,
            'isActive' => $this->isActive(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function userSummary(mixed $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
        ];
    }
}
