<?php

namespace App\Modules\HR\Resources;

use App\Modules\Attendance\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'clockIn' => $this->clock_in?->toIso8601String(),
            'clockOut' => $this->clock_out?->toIso8601String(),
            'status' => $this->status,
            'totalWorkedMinutes' => $this->total_worked_minutes,
            'actualWorkedMinutes' => $this->getWorkedMinutes(),
            'stoppedMinutes' => $this->clock_out ? max(0, (int) $this->clock_in?->diffInMinutes($this->clock_out) - $this->getWorkedMinutes()) : 0,
            'expectedWorkedMinutes' => $this->getExpectedWorkedMinutes(),
            'lateMinutes' => $this->late_minutes,
            'isLate' => $this->is_late,
            'idleMinutes' => $this->idle_minutes,
            'deductionMinutes' => $this->deduction_minutes,
            'usedGraceDay' => $this->used_grace_day,
            'expectedClockIn' => $this->expected_clock_in,
            'expectedClockOut' => $this->expected_clock_out,
            'notes' => $this->notes,
            'isClockedIn' => $this->isClockedIn(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ]),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
