<?php

namespace App\Http\Resources\V1\HR;

use App\Models\RewardType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RewardType */
class RewardTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var RewardType $rewardType */
        $rewardType = $this->resource;

        return [
            'id' => $rewardType->id,
            'name' => $rewardType->name,
            'code' => $rewardType->code,
            'description' => $rewardType->description,
            'calculation_type' => $rewardType->calculation_type,
            'base_amount' => $rewardType->base_amount,
            'is_active' => $rewardType->is_active,
            'requires_approval' => $rewardType->requires_approval,
            'criteria' => $rewardType->criteria,
            'multipliers' => $rewardType->multipliers,
            'employee_rewards_count' => $this->whenCounted('employeeRewards'),
            'created_at' => $rewardType->created_at?->toISOString(),
            'updated_at' => $rewardType->updated_at?->toISOString(),
        ];
    }
}
