<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmployeeLeave;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeLeave */
class EmployeeLeaveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EmployeeLeave $leave */
        $leave = $this->resource;

        return [
            'id' => $leave->id,
            'employeeId' => $leave->employee_id,
            'leaveType' => new LeaveTypeResource($this->whenLoaded('leaveType')),
            'leaveTypeId' => $leave->leave_type_id,
            'startDate' => $leave->start_date?->toDateString(),
            'endDate' => $leave->end_date?->toDateString(),
            'workingDays' => (float) $leave->working_days,
            'isPaid' => $leave->is_paid,
            'status' => $leave->status,
            'reason' => $leave->reason,
            'reviewedBy' => $leave->reviewed_by,
            'reviewedAt' => $leave->reviewed_at?->toISOString(),
            'reviewNote' => $leave->review_note,
            'createdAt' => $leave->created_at?->toISOString(),
        ];
    }
}
