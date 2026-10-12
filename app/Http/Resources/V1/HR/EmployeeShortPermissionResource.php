<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmployeeShortPermission;
use App\Policies\UserAdministrationPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeShortPermission */
class EmployeeShortPermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EmployeeShortPermission $permission */
        $permission = $this->resource;

        return [
            'id' => $permission->id,
            'employeeId' => $permission->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'userId' => $permission->employee->id,
                'name' => $permission->employee->name,
            ]),
            'canReview' => $this->whenLoaded('employee', fn () => app(UserAdministrationPolicy::class)->canReviewShortPermission($request->user(), $permission->employee)),
            'permissionDate' => $permission->permission_date?->toDateString(),
            'startTime' => substr((string) $permission->start_time, 0, 5),
            'endTime' => substr((string) $permission->end_time, 0, 5),
            'durationMinutes' => $permission->duration_minutes,
            'isPaid' => $permission->is_paid,
            'status' => $permission->status,
            'reason' => $permission->reason,
            'reviewedBy' => $permission->reviewed_by,
            'reviewedAt' => $permission->reviewed_at?->toISOString(),
            'reviewNote' => $permission->review_note,
            'policyVersionId' => $permission->policy_version_id,
            'createdAt' => $permission->created_at?->toISOString(),
        ];
    }
}
