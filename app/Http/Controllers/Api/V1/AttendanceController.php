<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Attendance\AttendanceResource;
use App\Models\Attendance;
use App\Services\Sales\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Attendance",
 *     description="Attendance management endpoints"
 * )
 */
class AttendanceController extends Controller
{
    public function __construct(
        private TimeTrackingService $timeTrackingService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/today",
     *     operationId="getTodayAttendance",
     *     tags={"Attendance"},
     *     summary="Get current user's attendance for today",
     *     description="Retrieve the authenticated user's attendance record for the current date, including active timer status and total worked minutes",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Today's attendance retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Today's attendance retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="attendance", ref="#/components/schemas/AttendanceResource", nullable=true),
     *                 @OA\Property(property="hasActiveTimer", type="boolean", example=false),
     *                 @OA\Property(property="totalWorkedMinutes", type="integer", example=240)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

        // Get today's attendance record
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        // Check if user has an active timer
        $activeTimer = $this->timeTrackingService->getActiveTimer($user);

        // Calculate total worked minutes for today
        $totalWorkedMinutes = \App\Models\TimeEntry::where('user_id', $user->id)
            ->whereDate('start_time', $today)
            ->whereNotNull('end_time')
            ->sum('duration_minutes');

        return ApiResponse::success([
            'attendance' => $attendance ? new AttendanceResource($attendance) : null,
            'hasActiveTimer' => $activeTimer !== null,
            'totalWorkedMinutes' => (int) $totalWorkedMinutes,
        ], "Today's attendance retrieved successfully");
    }

    /**
     * @OA\Post(
     *     path="/api/v1/attendance/clock-out",
     *     operationId="clockOut",
     *     tags={"Attendance"},
     *     summary="Clock out for the day",
     *     description="Set clock_out time for today's attendance record and calculate idle minutes. Requires an existing attendance record for today.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Clocked out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Clocked out successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AttendanceResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No attendance record found for today",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No attendance record found for today. Start a timer first to clock in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Already clocked out",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Already clocked out for today")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function clockOut(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Check if already clocked out
            $today = Carbon::today();
            $existingAttendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', $today)
                ->first();

            if ($existingAttendance && $existingAttendance->clock_out !== null) {
                return ApiResponse::error('Already clocked out for today', 400);
            }

            $attendance = $this->timeTrackingService->clockOut($user);

            return ApiResponse::success(
                new AttendanceResource($attendance),
                'Clocked out successfully'
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(
                'No attendance record found for today. Start a timer first to clock in.',
                404
            );
        }
    }
}
