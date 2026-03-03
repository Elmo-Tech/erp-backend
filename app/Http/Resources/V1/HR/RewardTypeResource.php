<?php

namespace App\Http\Resources\V1\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'calculation_type' => $this->calculation_type,
            'base_amount' => $this->base_amount,
            'is_active' => $this->is_active,
            'requires_approval' => $this->requires_approval,
            'criteria' => $this->criteria,
            'multipliers' => $this->multipliers,
            'employee_rewards_count' => $this->whenCounted('employeeRewards'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
