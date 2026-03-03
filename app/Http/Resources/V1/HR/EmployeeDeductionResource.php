<?php

namespace App\Http\Resources\V1\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDeductionResource extends JsonResource
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
            'deduction_type' => new DeductionTypeResource($this->whenLoaded('deductionType')),
            'deduction_date' => $this->deduction_date?->format('Y-m-d'),
            'amount' => $this->amount,
            'reason' => $this->reason,
            'status' => $this->status,
            'occurrences' => $this->occurrences,
            'approved_by' => $this->approver?->name,
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
            'salary_adjustment' => new SalaryAdjustmentResource($this->whenLoaded('salaryAdjustment')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
