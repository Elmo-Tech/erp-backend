<?php

namespace App\Modules\HR\Resources;

use App\Modules\HR\Models\EmployeeReward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeReward */
class EmployeeRewardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var EmployeeReward $reward */
        $reward = $this->resource;
        $user = $reward->user;
        $approver = $reward->approver;

        return [
            'id' => $reward->id,
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'reward_type' => new RewardTypeResource($this->whenLoaded('rewardType')),
            'reward_date' => $reward->reward_date?->format('Y-m-d'),
            'amount' => $reward->amount,
            'reason' => $reward->reason,
            'status' => $reward->status,
            'points_used' => $reward->points_used,
            'approved_by' => $approver?->name,
            'approved_at' => $reward->approved_at?->toISOString(),
            'rejection_reason' => $reward->rejection_reason,
            'paid_at' => $reward->paid_at?->format('Y-m-d'),
            'payment_reference' => $reward->payment_reference,
            'rewardable_type' => $reward->rewardable_type,
            'rewardable_id' => $reward->rewardable_id,
            'salary_adjustment' => new SalaryAdjustmentResource($this->whenLoaded('salaryAdjustment')),
            'created_at' => $reward->created_at?->toISOString(),
            'updated_at' => $reward->updated_at?->toISOString(),
        ];
    }
}
