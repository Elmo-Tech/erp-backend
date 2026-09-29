<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmployeeOvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeOvertimeRequest */
class EmployeeOvertimeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EmployeeOvertimeRequest $overtimeRequest */
        $overtimeRequest = $this->resource;

        return [
            'id' => $overtimeRequest->id,
            'employeeId' => $overtimeRequest->employee_id,
            'overtimeDate' => $overtimeRequest->overtime_date?->toDateString(),
            'startTime' => substr((string) $overtimeRequest->start_time, 0, 5),
            'endTime' => substr((string) $overtimeRequest->end_time, 0, 5),
            'requestedMinutes' => $overtimeRequest->requested_minutes,
            'actualMinutes' => $overtimeRequest->actual_minutes,
            'approvedMinutes' => $overtimeRequest->approved_minutes,
            'unapprovedMinutes' => $overtimeRequest->unapproved_minutes,
            'status' => $overtimeRequest->status,
            'reason' => $overtimeRequest->reason,
            'reviewedBy' => $overtimeRequest->reviewed_by,
            'reviewedAt' => $overtimeRequest->reviewed_at?->toISOString(),
            'reviewNote' => $overtimeRequest->review_note,
            'policyVersionId' => $overtimeRequest->policy_version_id,
            'hourlyRate' => $overtimeRequest->hourly_rate === null ? null : (float) $overtimeRequest->hourly_rate,
            'overtimeMultiplier' => $overtimeRequest->overtime_multiplier === null
                ? null
                : (float) $overtimeRequest->overtime_multiplier,
            'payableAmount' => $overtimeRequest->payable_amount === null ? null : (float) $overtimeRequest->payable_amount,
            'createdAt' => $overtimeRequest->created_at?->toISOString(),
        ];
    }
}
