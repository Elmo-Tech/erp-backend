<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmployeeLateDeduction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeLateDeduction */
class EmployeeLateDeductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EmployeeLateDeduction $deduction */
        $deduction = $this->resource;
        $finalAmount = $deduction->override_amount !== null
            ? (float) $deduction->override_amount
            : (float) $deduction->calculated_amount;

        return [
            'id' => $deduction->id,
            'employeeId' => $deduction->employee_id,
            'attendanceId' => $deduction->attendance_id,
            'attendanceDate' => $deduction->attendance_date?->toDateString(),
            'originalLateMinutes' => $deduction->original_late_minutes,
            'eligibleLateMinutes' => $deduction->eligible_late_minutes,
            'occurrenceNumber' => $deduction->occurrence_number,
            'tierMinimumMinutes' => $deduction->tier_minimum_minutes,
            'tierMaximumMinutes' => $deduction->tier_maximum_minutes,
            'percentage' => (float) $deduction->percentage,
            'dailyRate' => (float) $deduction->daily_rate,
            'calculatedAmount' => (float) $deduction->calculated_amount,
            'overrideAmount' => $deduction->override_amount !== null ? (float) $deduction->override_amount : null,
            'finalAmount' => $finalAmount,
            'status' => $deduction->status,
            'waiverReason' => $deduction->waiver_reason,
            'overrideReason' => $deduction->override_reason,
            'policyVersionId' => $deduction->policy_version_id,
            'salaryAdjustmentId' => $deduction->salary_adjustment_id,
            'evaluatedAt' => $deduction->evaluated_at?->toISOString(),
        ];
    }
}
