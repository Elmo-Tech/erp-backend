<?php

namespace App\Http\Resources\V1\HR;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveType */
class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LeaveType $leaveType */
        $leaveType = $this->resource;

        return [
            'id' => $leaveType->id,
            'name' => $leaveType->name,
            'slug' => $leaveType->slug,
            'annualAllowanceDays' => $leaveType->annual_allowance_days,
            'isPaid' => $leaveType->is_paid,
            'isActive' => $leaveType->is_active,
            'displayOrder' => $leaveType->display_order,
        ];
    }
}
