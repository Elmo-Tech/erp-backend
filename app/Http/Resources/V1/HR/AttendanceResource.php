<?php

namespace App\Http\Resources\V1\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="HRAttendanceResource",
 *     type="object",
 *     @OA\Property(property="attendanceId", type="integer", example=1),
 *     @OA\Property(property="userId", type="integer", example=1),
 *     @OA\Property(property="date", type="string", format="date", example="2026-01-05"),
 *     @OA\Property(property="clockIn", type="string", format="date-time"),
 *     @OA\Property(property="clockOut", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="expectedClockIn", type="string", example="10:00:00"),
 *     @OA\Property(property="expectedClockOut", type="string", example="18:00:00"),
 *     @OA\Property(property="lateMinutes", type="integer", example=15),
 *     @OA\Property(property="idleMinutes", type="integer", example=30),
 *     @OA\Property(property="deductionMinutes", type="integer", example=0),
 *     @OA\Property(property="usedGraceDay", type="boolean", example=false),
 *     @OA\Property(property="workedMinutes", type="integer", example=480),
 *     @OA\Property(property="isClockedIn", type="boolean", example=true),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="user", type="object", nullable=true)
 * )
 */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'attendanceId' => $this->id,
            'userId' => $this->user_id,
            'date' => $this->date?->format('Y-m-d'),
            'clockIn' => $this->clock_in?->toISOString(),
            'clockOut' => $this->clock_out?->toISOString(),
            'expectedClockIn' => $this->expected_clock_in,
            'expectedClockOut' => $this->expected_clock_out,
            'lateMinutes' => (int) $this->late_minutes,
            'idleMinutes' => (int) $this->idle_minutes,
            'deductionMinutes' => (int) $this->deduction_minutes,
            'usedGraceDay' => (bool) $this->used_grace_day,
            'workedMinutes' => $this->getWorkedMinutes(),
            'isClockedIn' => $this->isClockedIn(),
            'notes' => $this->notes ?? '',
            
            'user' => $this->whenLoaded('user', fn() => [
                'userId' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'avatar' => $this->user->avatar ?? '',
            ]),
        ];
    }
}
