<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\HR\AttendanceResource;
use App\Http\Resources\V1\HR\AttendanceCollection;
use App\Models\Attendance;
use App\Services\HR\AttendanceService;
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
        private TimeTrackingService $timeTrackingService,
        private AttendanceService $attendanceService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/today",
     *     operationId="getTodayAttendance",
     *     tags={"Attendance"},
     *     summary="Get current user's attendance for today",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

        $attendance = $this->attendanceService->getTodayAttendance($user);
        $activeTimer = $this->timeTrackingService->getActiveTimer($user);

        $totalWorkedMinutes = \App\Models\TimeEntry::where('user_id', $user->id)
            ->whereDate('start_time', $today)
            ->whereNotNull('end_time')
            ->sum('duration_minutes');

        return ApiResponse::success([
            'attendance' => $attendance ? new AttendanceResource($attendance) : null,
            'hasActiveTimer' => $activeTimer !== null,
            'totalWorkedMinutes' => (int) $totalWorkedMinutes,
            'expectedClockOut' => $user->expected_clock_out,
            'graceDaysRemaining' => max(0, $user->grace_days_allowed - $user->grace_days_used),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/attendance/clock-out",
     *     operationId="clockOut",
     *     tags={"Attendance"},
     *     summary="Clock out for the day",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function clockOut(Request $request): JsonResponse
    {
        $user = $request->user();

        $attendance = $this->attendanceService->clockOut($user);

        if (!$attendance) {
            return ApiResponse::error(__('messages.no_attendance_record'), 404);
        }

        return ApiResponse::success(
            new AttendanceResource($attendance),
            __('messages.clocked_out')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/history",
     *     operationId="getAttendanceHistory",
     *     tags={"Attendance"},
     *     summary="Get attendance history for current user",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('perPage', 15);

        $attendances = $this->attendanceService->getUserAttendance(
            $user,
            $request->query('dateFrom'),
            $request->query('dateTo'),
            $perPage
        );

        return ApiResponse::success(new AttendanceCollection($attendances));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/summary",
     *     operationId="getAttendanceSummary",
     *     tags={"Attendance"},
     *     summary="Get monthly attendance summary",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('m'));

        $summary = $this->attendanceService->getMonthlySummary($user, $year, $month);

        return ApiResponse::success($summary);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/all",
     *     operationId="getAllAttendance",
     *     tags={"Attendance"},
     *     summary="Get all employees attendance (admin)",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function all(Request $request): JsonResponse
    {
        if (!$request->user()->can('view_all_attendance')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $filters = [
            'userId' => $request->query('filter.userId'),
            'startDate' => $request->query('filter.startDate'),
            'endDate' => $request->query('filter.endDate'),
            'date' => $request->query('filter.date'),
        ];

        $perPage = (int) $request->query('perPage', 15);
        $attendances = $this->attendanceService->getAllAttendance($filters, $perPage);

        return ApiResponse::success(new AttendanceCollection($attendances));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/attendance",
     *     operationId="storeAttendance",
     *     tags={"Attendance"},
     *     summary="Create attendance record (admin)",
     *     description="Creates attendance record and auto-calculates late minutes, grace day usage, and deductions based on clock-in time vs expected time.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"userId", "date", "clockIn"},
     *             @OA\Property(property="userId", type="integer", example=1),
     *             @OA\Property(property="date", type="string", format="date", example="2026-01-06"),
     *             @OA\Property(property="clockIn", type="string", format="date-time", example="2026-01-06T10:00:00"),
     *             @OA\Property(property="clockOut", type="string", format="date-time", example="2026-01-06T18:00:00", nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('manage_attendance')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'clockIn' => 'required|date',
            'clockOut' => 'nullable|date|after:clockIn',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check if attendance already exists for this user on this date
        $existing = Attendance::where('user_id', $validated['userId'])
            ->where('date', $validated['date'])
            ->first();

        if ($existing) {
            return ApiResponse::error(__('messages.attendance_already_exists'), 422);
        }

        $attendance = $this->attendanceService->createAttendance($validated, $request->user());

        return ApiResponse::success(
            new AttendanceResource($attendance),
            __('messages.attendance_created'),
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/attendance/{id}",
     *     operationId="updateAttendance",
     *     tags={"Attendance"},
     *     summary="Update attendance record (admin)",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->can('manage_attendance')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $attendance = Attendance::find($id);

        if (!$attendance) {
            return ApiResponse::error(__('messages.attendance_not_found'), 404);
        }

        $attendance = $this->attendanceService->updateAttendance(
            $attendance,
            $request->all(),
            $request->user()
        );

        return ApiResponse::success(
            new AttendanceResource($attendance),
            __('messages.attendance_updated')
        );
    }
}
