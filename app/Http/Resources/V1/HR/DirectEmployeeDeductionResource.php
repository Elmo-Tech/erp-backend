<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmployeeDeduction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeDeduction */
class DirectEmployeeDeductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EmployeeDeduction $deduction */
        $deduction = $this->resource;

        return [
            'id' => $deduction->id,
            'employeeId' => $deduction->user_id,
            'deductionType' => [
                'id' => $deduction->deductionType?->id,
                'name' => $deduction->deductionType?->name,
            ],
            'deductionDate' => $deduction->deduction_date?->toDateString(),
            'amount' => (float) $deduction->amount,
            'status' => $deduction->status,
            'reason' => $deduction->reason,
            'approvedBy' => $deduction->approved_by,
            'approvedAt' => $deduction->approved_at?->toISOString(),
            'rejectionReason' => $deduction->rejection_reason,
            'salaryAdjustmentId' => $deduction->salaryAdjustment?->id,
            'createdAt' => $deduction->created_at?->toISOString(),
        ];
    }
}
