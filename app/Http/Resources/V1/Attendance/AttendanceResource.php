<?php

namespace App\Http\Resources\V1\Attendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="AttendanceResource",
 *     type="object",
 *     title="Attendance Resource",
 *     description="Attendance record resource representation",
 *     @OA\Property(property="attendanceId", type="integer", example=1),
 *     @OA\Property(property="userId", type="integer", example=1),
 *     @OA\Property(property="date", type="string", format="date", example="2026-01-01"),
 *     @OA\Property(property="clockIn", type="string", format="date-time", example="2026-01-01T09:00:00.000000Z"),
 *     @OA\Property(property="clockOut", type="string", format="date-time", nullable=true, example="2026-01-01T17:00:00.000000Z"),
 *     @OA\Property(property="lateMinutes", type="integer", example=0),
 *     @OA\Property(property="idleMinutes", type="integer", example=30),
 *     @OA\Property(property="createdAt", type="string", format="date-time", example="2026-01-01T09:00:00.000000Z"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time", example="2026-01-01T17:00:00.000000Z"),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="userId", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", example="john@example.com")
 *     )
 * )
 */
class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * 
     * Transforms all fields to camelCase format as per API conventions.
     * 
     * Requirements: 3.3
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'attendanceId' => $this->id ?? 0,
            'userId' => $this->user_id ?? 0,
            'date' => $this->date?->format('Y-m-d') ?? '',
            'clockIn' => $this->clock_in?->toISOString() ?? '',
            'clockOut' => $this->clock_out?->toISOString(),
            'lateMinutes' => $this->late_minutes ?? 0,
            'idleMinutes' => $this->idle_minutes ?? 0,
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            // Include user relationship when loaded
            'user' => $this->whenLoaded('user', function () {
                return [
                    'userId' => $this->user->id ?? 0,
                    'name' => $this->user->name ?? '',
                    'email' => $this->user->email ?? '',
                ];
            }),
        ];
    }
}
