<?php

namespace App\Http\Resources\V1\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeRewardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'reward_type' => new RewardTypeResource($this->whenLoaded('rewardType')),
            'reward_date' => $this->reward_date?->format('Y-m-d'),
            'amount' => $this->amount,
            'reason' => $this->reason,
            'status' => $this->status,
            'points_used' => $this->points_used,
            'approved_by' => $this->approver?->name,
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'paid_at' => $this->paid_at?->format('Y-m-d'),
            'payment_reference' => $this->payment_reference,
            'rewardable_type' => $this->rewardable_type,
            'rewardable_id' => $this->rewardable_id,
            'salary_adjustment' => new SalaryAdjustmentResource($this->whenLoaded('salaryAdjustment')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
