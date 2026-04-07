<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmployeeDeduction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeDeduction */
class EmployeeDeductionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var EmployeeDeduction $deduction */
        $deduction = $this->resource;
        $user = $deduction->user;
        $approver = $deduction->approver;

        return [
            'id' => $deduction->id,
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'deduction_type' => new DeductionTypeResource($this->whenLoaded('deductionType')),
            'deduction_date' => $deduction->deduction_date?->format('Y-m-d'),
            'amount' => $deduction->amount,
            'reason' => $deduction->reason,
            'status' => $deduction->status,
            'occurrences' => $deduction->getAttribute('occurrences'),
            'approved_by' => $approver?->name,
            'approved_at' => $deduction->approved_at?->toISOString(),
            'rejection_reason' => $deduction->rejection_reason,
            'notes' => $deduction->getAttribute('notes'),
            'salary_adjustment' => new SalaryAdjustmentResource($this->whenLoaded('salaryAdjustment')),
            'created_at' => $deduction->created_at?->toISOString(),
            'updated_at' => $deduction->updated_at?->toISOString(),
        ];
    }
}
