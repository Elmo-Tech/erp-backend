<?php

namespace App\Http\Resources\V1\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="EmployeeResource",
 *     type="object",
 *     @OA\Property(property="userId", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com"),
 *     @OA\Property(property="avatar", type="string", nullable=true),
 *     @OA\Property(property="isActive", type="boolean", example=true),
 *     @OA\Property(property="role", type="string", example="developer"),
 *     @OA\Property(property="workHoursPerDay", type="integer", example=8),
 *     @OA\Property(property="workStartTime", type="string", example="10:00:00"),
 *     @OA\Property(property="expectedClockOut", type="string", example="18:00:00"),
 *     @OA\Property(property="graceDaysAllowed", type="integer", example=5),
 *     @OA\Property(property="graceDaysUsed", type="integer", example=2),
 *     @OA\Property(property="graceDaysRemaining", type="integer", example=3),
 *     @OA\Property(property="totalPoints", type="integer", example=150),
 *     @OA\Property(property="skills", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="createdAt", type="string", format="date-time")
 * )
 */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewSalary = $user && $user->can('view_employee_salary');

        return [
            'userId' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar ?? '',
            'isActive' => (bool) $this->is_active,
            'role' => $this->roles->first()?->name ?? '',
            
            // Work schedule
            'workHoursPerDay' => (int) $this->work_hours_per_day,
            'workStartTime' => $this->work_start_time,
            'expectedClockOut' => $this->expected_clock_out,
            
            // Grace days
            'graceDaysAllowed' => (int) $this->grace_days_allowed,
            'graceDaysUsed' => (int) $this->grace_days_used,
            'graceDaysRemaining' => max(0, $this->grace_days_allowed - $this->grace_days_used),
            
            // Performance
            'totalPoints' => (int) $this->total_points,
            'skills' => $this->skills ?? [],
            
            // Salary info (admin only)
            'salary' => $canViewSalary ? (float) ($this->salary ?? 0) : null,
            'totalDeductions' => $canViewSalary ? (float) ($this->total_deductions ?? 0) : null,
            'internalHourlyCost' => $canViewSalary ? (float) ($this->internal_hourly_cost ?? 0) : null,
            
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }
}
