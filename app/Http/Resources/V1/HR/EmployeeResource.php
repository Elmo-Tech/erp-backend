<?php

namespace App\Http\Resources\V1\HR;

use App\Models\User;
use App\Services\HR\SalaryRateCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="EmployeeResource",
 *     type="object",
 *
 *     @OA\Property(property="userId", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com"),
 *     @OA\Property(property="avatar", type="string", nullable=true),
 *     @OA\Property(property="isActive", type="boolean", example=true),
 *     @OA\Property(property="role", type="string", example="developer"),
 *     @OA\Property(property="specialization", type="string", enum={"backend", "frontend", "fullstack", "ui_ux", "mobile", "devops", "qa"}, example="backend"),
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
    /** @mixin User */
    public function toArray(Request $request): array
    {
        /** @var User $employee */
        $employee = $this->resource;
        $user = $request->user();
        $simplifiedMode = (bool) config('simplification.enabled');
        $canViewSalary = $user
            && $user->hasAnyPermission(['view_employee_salary', 'manage_employees']);
        $roleName = $employee->roles->pluck('name')->first() ?? '';
        $graceDaysAllowed = $employee->grace_days_allowed ?? 0;
        $graceDaysUsed = $employee->grace_days_used ?? 0;

        $data = [
            'userId' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'avatar' => $employee->avatar,
            'isActive' => (bool) $employee->is_active,
            'role' => $roleName,
            'specialization' => $employee->specialization ?? '',

            // Work schedule
            'workHoursPerDay' => (int) ($employee->work_hours_per_day ?? 0),
            'monthlyWorkHours' => app(SalaryRateCalculator::class)
                ->monthlyWorkHours((float) ($employee->work_hours_per_day ?? 0)),
            'workStartTime' => $employee->work_start_time,
            'expectedClockOut' => $employee->expected_clock_out,

            // Grace days
            'graceDaysAllowed' => (int) $graceDaysAllowed,
            'graceDaysUsed' => (int) $graceDaysUsed,
            'graceDaysRemaining' => max(0, $graceDaysAllowed - $graceDaysUsed),

            'skills' => $employee->skills ?? [],

            // Salary info (admin only)
            'salary' => $canViewSalary ? (float) ($employee->salary ?? 0) : null,
            'hourlyRate' => $canViewSalary
                ? ($employee->hourly_rate === null ? null : (float) $employee->hourly_rate)
                : null,
            'totalDeductions' => $canViewSalary ? (float) ($employee->total_deductions ?? 0) : null,
            'internalHourlyCost' => $canViewSalary ? (float) ($employee->internal_hourly_cost ?? 0) : null,

            'createdAt' => $employee->created_at?->toISOString() ?? '',
        ];

        if (! $simplifiedMode) {
            $data['totalPoints'] = (int) ($employee->total_points ?? 0);
        }

        return $data;
    }
}
