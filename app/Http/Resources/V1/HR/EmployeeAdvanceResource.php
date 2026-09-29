<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeAdvance */
class EmployeeAdvanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EmployeeAdvance $advance */
        $advance = $this->resource;
        $settlements = $advance->relationLoaded('settlements')
            ? $advance->settlements
            : $advance->settlements()->get();

        return [
            'id' => $advance->id,
            'employeeId' => $advance->employee_id,
            'requestedAmount' => (float) $advance->requested_amount,
            'approvedAmount' => $advance->approved_amount === null ? null : (float) $advance->approved_amount,
            'disbursedAmount' => $advance->disbursed_amount === null ? null : (float) $advance->disbursed_amount,
            'outstandingAmount' => $advance->outstanding_amount,
            'status' => $advance->status,
            'reason' => $advance->reason,
            'installmentCount' => $advance->installment_count,
            'policyVersionId' => $advance->policy_version_id,
            'policySnapshot' => $advance->policy_snapshot,
            'salaryAtApproval' => $advance->salary_at_approval === null ? null : (float) $advance->salary_at_approval,
            'advanceLimitAmount' => $advance->advance_limit_amount === null ? null : (float) $advance->advance_limit_amount,
            'approvedBy' => $advance->approved_by,
            'approvedAt' => $advance->approved_at?->toISOString(),
            'reviewNote' => $advance->review_note,
            'disbursedBy' => $advance->disbursed_by,
            'disbursedAt' => $advance->disbursed_at?->toISOString(),
            'disbursementReference' => $advance->disbursement_reference,
            'settlements' => $settlements->map(
                fn (EmployeeAdvanceSettlement $settlement): array => [
                    'id' => $settlement->id,
                    'employeeAdvanceId' => $settlement->employee_advance_id,
                    'sequence' => $settlement->sequence,
                    'dueOn' => $settlement->due_on?->toDateString(),
                    'scheduledAmount' => (float) $settlement->scheduled_amount,
                    'status' => $settlement->status,
                    'settledBy' => $settlement->settled_by,
                    'settledAt' => $settlement->settled_at?->toISOString(),
                    'settlementReference' => $settlement->settlement_reference,
                    'salaryAdjustmentId' => $settlement->salary_adjustment_id,
                    'createdAt' => $settlement->created_at?->toISOString(),
                    'updatedAt' => $settlement->updated_at?->toISOString(),
                ],
            )->values()->all(),
            'createdAt' => $advance->created_at?->toISOString(),
            'updatedAt' => $advance->updated_at?->toISOString(),
        ];
    }
}
