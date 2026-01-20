<?php

namespace App\Http\Resources\V1\UserPoints;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPointResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'userPointId' => $this->id ?? 0,
            'userId' => $this->user_id ?? 0,
            'pointsChange' => $this->points_change ?? 0,
            'pointsBefore' => $this->points_before ?? 0,
            'pointsAfter' => $this->points_after ?? 0,
            'reason' => $this->reason ?? "",
            'type' => $this->type ?? "manual",
            'transactionType' => $this->transaction_type?->value ?? $this->transaction_type ?? null,
            'createdBy' => $this->whenLoaded('createdBy', fn() => $this->createdBy->name ?? ""),
            'createdAt' => $this->created_at?->toISOString() ?? "",
            'updatedAt' => $this->updated_at?->toISOString() ?? "",
        ];
    }
}
