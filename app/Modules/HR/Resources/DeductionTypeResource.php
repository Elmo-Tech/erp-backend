<?php

namespace App\Modules\HR\Resources;

use App\Modules\HR\Models\DeductionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeductionType
 */
class DeductionTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var DeductionType $deductionType */
        $deductionType = $this->resource;

        return [
            'id' => $deductionType->id,
            'name' => $deductionType->name,
            'code' => $deductionType->code,
            'description' => $deductionType->description,
            'category' => null,
            'is_active' => $deductionType->is_active,
            'requires_approval' => $deductionType->requires_approval,
            'auto_calculate' => false,
            'default_amount' => $deductionType->amount,
            'max_amount' => null,
            'frequency' => null,
            'rules' => DeductionRuleResource::collection($this->whenLoaded('deductionRules')),
            'rules_count' => $this->whenCounted('deductionRules'),
            'employee_deductions_count' => $this->whenCounted('employeeDeductions'),
            'created_at' => $deductionType->created_at?->toISOString(),
            'updated_at' => $deductionType->updated_at?->toISOString(),
        ];
    }
}
