<?php

namespace App\Modules\HR\Resources;

use App\Modules\HR\Models\DeductionRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeductionRule
 */
class DeductionRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var DeductionRule $rule */
        $rule = $this->resource;

        return [
            'id' => $rule->id,
            'deduction_type_id' => $rule->deduction_type_id,
            'name' => $rule->name,
            'code' => $rule->code,
            'description' => $rule->description,
            'rule_type' => $rule->rule_type,
            'threshold_value' => $rule->threshold_value,
            'threshold_unit' => $rule->threshold_unit,
            'deduction_value' => $rule->deduction_value,
            'deduction_unit' => $rule->deduction_unit,
            'max_deduction_amount' => $rule->max_deduction_amount,
            'repetition_penalty_factor' => $rule->repetition_penalty_factor,
            'grace_period_days' => $rule->grace_period_days,
            'is_active' => $rule->is_active,
            'effective_from' => $rule->effective_from?->format('Y-m-d'),
            'effective_until' => $rule->effective_until?->format('Y-m-d'),
            'conditions' => $rule->conditions,
            'is_effective' => $rule->isEffective(),
            'created_at' => $rule->created_at?->toISOString(),
            'updated_at' => $rule->updated_at?->toISOString(),
        ];
    }
}
