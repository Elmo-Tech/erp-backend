<?php

namespace App\Http\Resources\V1\HR;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 *
 * @OA\Schema(
 *     schema="HRAttendanceResource",
 *     type="object",
 *
 *     @OA\Property(property="attendanceId", type="integer", example=1),
 *     @OA\Property(property="userId", type="integer", example=1),
 *     @OA\Property(property="date", type="string", format="date", example="2026-01-05"),
 *     @OA\Property(property="clockIn", type="string", format="date-time"),
 *     @OA\Property(property="clockOut", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="expectedClockIn", type="string", example="10:00:00"),
 *     @OA\Property(property="expectedClockOut", type="string", example="18:00:00"),
 *     @OA\Property(property="lateMinutes", type="integer", example=15),
 *     @OA\Property(property="isLate", type="boolean", example=false),
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
        /** @var Attendance $attendance */
        $attendance = $this->resource;
        $user = $request->user();
        $canViewDeductions = $user && ($user->can('manage_attendance') || $user->can('view_all_attendance'));

        $data = [
            'attendanceId' => $attendance->id,
            'userId' => $attendance->user_id,
            'date' => $attendance->date?->format('Y-m-d'),
            'clockIn' => $attendance->clock_in?->format('Y-m-d\TH:i:s'),
            'clockOut' => $attendance->clock_out?->format('Y-m-d\TH:i:s'),
            'expectedClockIn' => $attendance->expected_clock_in,
            'expectedClockOut' => $attendance->expected_clock_out,
            'lateMinutes' => (int) $attendance->late_minutes,
            'isLate' => $attendance->is_late,
            'idleMinutes' => (int) $attendance->idle_minutes,
            'usedGraceDay' => $attendance->used_grace_day,
            'workedMinutes' => $attendance->getWorkedMinutes(),
            'isClockedIn' => $attendance->isClockedIn(),
            'notes' => $attendance->notes ?? '',

            'user' => $this->whenLoaded('user', fn () => [
                'userId' => $attendance->user->id,
                'name' => $attendance->user->name,
                'email' => $attendance->user->email,
                'avatar' => $attendance->user->avatar,
                'graceDaysRemaining' => max(0, $attendance->user->grace_days_allowed - $attendance->user->grace_days_used),
            ]),
        ];

        if ($canViewDeductions) {
            $data['deductionMinutes'] = (int) $attendance->deduction_minutes;
        }

        return $data;
    }
}
