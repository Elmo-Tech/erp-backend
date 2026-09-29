<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmployeePartialPay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeePartialPay */
class EmployeePartialPayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EmployeePartialPay $partialPay */
        $partialPay = $this->resource;
        $isRecord = $partialPay->exists;
        $salaryAdjustment = $partialPay->relationLoaded('salaryAdjustment')
            ? $partialPay->salaryAdjustment
            : null;

        return [
            'id' => $this->when($isRecord, $partialPay->id),
            'employeeId' => $partialPay->employee_id,
            'employmentContractId' => $partialPay->employment_contract_id,
            'kind' => $partialPay->kind,
            'payrollYear' => $partialPay->payroll_year,
            'payrollMonth' => $partialPay->payroll_month,
            'serviceStartDate' => $partialPay->service_start_date?->toDateString(),
            'serviceEndDate' => $partialPay->service_end_date?->toDateString(),
            'payableDays' => $partialPay->payable_days,
            'baseSalarySnapshot' => (float) $partialPay->base_salary_snapshot,
            'dailyRateSnapshot' => (float) $partialPay->daily_rate_snapshot,
            'grossAmount' => (float) $partialPay->gross_amount,
            'policyVersionId' => $partialPay->policy_version_id,
            'policySnapshot' => $partialPay->policy_snapshot,
            'contractWorkDays' => $partialPay->contract_work_days,
            'status' => $this->when($isRecord, $partialPay->status),
            'createdBy' => $this->when($isRecord, $partialPay->created_by),
            'updatedBy' => $this->when($isRecord, $partialPay->updated_by),
            'approvedBy' => $this->when($isRecord, $partialPay->approved_by),
            'approvedAt' => $this->when($isRecord, $partialPay->approved_at?->toISOString()),
            'reviewNote' => $this->when($isRecord, $partialPay->review_note),
            'salaryAdjustmentId' => $this->when($isRecord, $partialPay->salary_adjustment_id),
            'salaryAdjustmentStatus' => $this->when($isRecord, $salaryAdjustment?->status),
            'createdAt' => $this->when($isRecord, $partialPay->created_at?->toISOString()),
            'updatedAt' => $this->when($isRecord, $partialPay->updated_at?->toISOString()),
        ];
    }
}
