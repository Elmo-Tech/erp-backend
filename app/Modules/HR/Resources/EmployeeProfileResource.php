<?php

namespace App\Modules\HR\Resources;

use App\Modules\HR\Models\EmployeeProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeProfile
 */
class EmployeeProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewSalary = $request->user()?->hasAnyPermission([
            'view_employee_salary',
            'manage_employees',
        ]) ?? false;

        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'shiftId' => $this->shift_id,
            'baseSalary' => $this->when($canViewSalary, $this->base_salary),
            'hourlyRate' => $this->when($canViewSalary, $this->hourly_rate),
            'joinDate' => $this->join_date?->toDateString(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'avatar' => $this->user->avatar,
            ]),
            'shift' => $this->whenLoaded('shift', fn () => new ShiftResource($this->shift)),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
