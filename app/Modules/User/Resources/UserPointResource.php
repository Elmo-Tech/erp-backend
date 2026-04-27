<?php

namespace App\Modules\User\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPointResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * This resource works with PointTransaction as the single source of truth.
     * Field names are normalized for frontend compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $points = $this->points ?? $this->points_change ?? 0;
        $balanceAfter = $this->balance_after ?? $this->points_after ?? 0;

        // Get type label if it's an enum
        $typeLabel = $this->type?->label()
            ?? ($this->type instanceof \BackedEnum ? $this->type->value : $this->type)
            ?? 'Manual';

        return [
            'id' => $this->id ?? 0,
            'userId' => $this->user_id ?? 0,
            'points' => $points,
            'type' => $typeLabel,
            'reason' => $this->reason ?? '',
            'createdBy' => $this->whenLoaded('createdBy', fn () => $this->createdBy->name ?? 'System'),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'balanceAfter' => $balanceAfter,
        ];
    }
}
