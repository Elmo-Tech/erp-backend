<?php

namespace App\Http\Resources\V1\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeductionRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deduction_type_id' => $this->deduction_type_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'rule_type' => $this->rule_type,
            'threshold_value' => $this->threshold_value,
            'threshold_unit' => $this->threshold_unit,
            'deduction_value' => $this->deduction_value,
            'deduction_unit' => $this->deduction_unit,
            'max_deduction_amount' => $this->max_deduction_amount,
            'repetition_penalty_factor' => $this->repetition_penalty_factor,
            'grace_period_days' => $this->grace_period_days,
            'is_active' => $this->is_active,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_until' => $this->effective_until?->format('Y-m-d'),
            'conditions' => $this->conditions,
            'is_effective' => $this->isEffective(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
