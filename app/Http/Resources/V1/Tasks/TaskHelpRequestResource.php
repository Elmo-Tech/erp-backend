<?php

namespace App\Http\Resources\V1\Tasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskHelpRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'helpRequestId' => $this->id,
            'taskId' => $this->task_id,
            'requestedById' => $this->requested_by,
            'requestedBy' => $this->whenLoaded('requester', fn () => $this->userSummary($this->requester)),
            'message' => $this->message,
            'status' => $this->status,
            'acknowledgedById' => $this->acknowledged_by,
            'acknowledgedBy' => $this->whenLoaded('acknowledgedBy', fn () => $this->userSummary($this->acknowledgedBy)),
            'acknowledgedAt' => $this->acknowledged_at?->toISOString(),
            'resolvedById' => $this->resolved_by,
            'resolvedBy' => $this->whenLoaded('resolvedBy', fn () => $this->userSummary($this->resolvedBy)),
            'resolvedAt' => $this->resolved_at?->toISOString(),
            'resolutionNote' => $this->resolution_note,
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
