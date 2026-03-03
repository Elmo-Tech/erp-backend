<?php

namespace App\Http\Resources\V1\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeductionTypeResource extends JsonResource
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
            'category' => $this->category,
            'is_active' => $this->is_active,
            'requires_approval' => $this->requires_approval,
            'auto_calculate' => $this->auto_calculate,
            'default_amount' => $this->default_amount,
            'max_amount' => $this->max_amount,
            'frequency' => $this->frequency,
            'rules' => DeductionRuleResource::collection($this->whenLoaded('rules')),
            'rules_count' => $this->whenCounted('rules'),
            'employee_deductions_count' => $this->whenCounted('employeeDeductions'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
