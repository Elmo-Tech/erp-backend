<?php

namespace App\Modules\HR\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryAdjustmentResource extends JsonResource
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
            ],
            'adjustment_type' => $this->adjustment_type,
            'amount' => $this->amount,
            'signed_amount' => $this->signed_amount,
            'reason' => $this->reason,
            'adjustment_date' => $this->adjustment_date?->format('Y-m-d'),
            'payroll_month' => $this->payroll_month,
            'payroll_year' => $this->payroll_year,
            'status' => $this->status,
            'approved_by' => $this->approver?->name,
            'approved_at' => $this->approved_at?->toISOString(),
            'notes' => $this->notes,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
