<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\HR\AttendanceCollection;
use App\Http\Resources\V1\HR\AttendanceResource;
use App\Http\Resources\V1\HR\EmployeeDeductionResource;
use App\Models\Attendance;
use App\Models\DeductionType;
use App\Models\EmployeeDeduction;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\HR\AttendanceService;
use App\Services\Sales\TimeTrackingService;
use Carbon\Carbon;
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
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function today(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidClientAttendance($request)) {
            return $forbidden;
        }

        $user = $request->user();
        $today = Carbon::today();

        $attendance = $this->attendanceService->getTodayAttendance($user);
        $activeTimer = $this->timeTrackingService->getActiveTimer($user);

        $totalWorkedMinutes = TimeEntry::where('user_id', $user->id)
            ->whereDate('start_time', $today)
            ->whereNotNull('end_time')
            ->sum('duration_minutes');

        return ApiResponse::success([
            'attendance' => $attendance ? new AttendanceResource($attendance) : null,
            'hasActiveTimer' => $activeTimer !== null,
            'totalWorkedMinutes' => (int) $totalWorkedMinutes,
            'currentIdleMinutes' => $attendance?->clock_out ? (int) $attendance->idle_minutes : null,
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
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function clockOut(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidClientAttendance($request)) {
            return $forbidden;
        }

        $user = $request->user();

        $attendance = $this->attendanceService->clockOut($user);

        if (! $attendance) {
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
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function history(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidClientAttendance($request)) {
            return $forbidden;
        }

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
     *
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function summary(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidClientAttendance($request)) {
            return $forbidden;
        }

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
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function all(Request $request): JsonResponse
    {
        if (! $request->user()->can('view_all_attendance')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $filter = $request->query('filter', []);
        if (! is_array($filter)) {
            $filter = [];
        }

        $filters = [
            'userId' => $filter['userId'] ?? $request->query('filter.userId') ?? $request->query('filter_userId') ?? $request->query('userId'),
            'startDate' => $filter['startDate'] ?? $request->query('filter.startDate') ?? $request->query('filter_startDate') ?? $request->query('startDate'),
            'endDate' => $filter['endDate'] ?? $request->query('filter.endDate') ?? $request->query('filter_endDate') ?? $request->query('endDate'),
            'date' => $filter['date'] ?? $request->query('filter.date') ?? $request->query('filter_date') ?? $request->query('date'),
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
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"userId", "date", "clockIn"},
     *
     *             @OA\Property(property="userId", type="integer", example=1),
     *             @OA\Property(property="date", type="string", format="date", example="2026-01-06"),
     *             @OA\Property(property="clockIn", type="string", format="date-time", example="2026-01-06T10:00:00"),
     *             @OA\Property(property="clockOut", type="string", format="date-time", example="2026-01-06T18:00:00", nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->can('manage_attendance')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'clockIn' => 'required|date',
            'clockOut' => 'nullable|date|after:clockIn',
            'notes' => 'nullable|string|max:500',
            'isGraceDay' => 'nullable|boolean',
            'deductionMinutes' => 'nullable|integer|min:0',
        ]);

        // Check if attendance already exists for this user on this date
        $existing = Attendance::where('user_id', $validated['userId'])
            ->where('date', $validated['date'])
            ->first();

        if ($existing) {
            return ApiResponse::error(__('messages.attendance_already_exists'), 422);
        }

        $attendance = $this->attendanceService->createAttendance($validated, $request->user());

        // Handle manual grace day if specified
        if (! empty($validated['isGraceDay'])) {
            $user = $attendance->user;
            if ($user->hasGraceDaysRemaining()) {
                $user->useGraceDay();
                $attendance->update(['used_grace_day' => true]);
            }
        }

        // Handle manual deduction if specified
        if (! empty($validated['deductionMinutes']) && $validated['deductionMinutes'] > 0) {
            $user = $attendance->user;
            $this->createAttendanceDeduction($attendance, $user, $validated['deductionMinutes']);
        }

        return ApiResponse::success(
            new AttendanceResource($attendance->fresh()->load('user')),
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
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->can('manage_attendance')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $attendance = Attendance::find($id);

        if (! $attendance) {
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

    /**
     * Add a deduction to an existing attendance record
     */
    public function addDeduction(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->can('manage_attendance')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $attendance = Attendance::find($id);

        if (! $attendance) {
            return ApiResponse::error(__('messages.attendance_not_found'), 404);
        }

        $validated = $request->validate([
            'deduction_type_id' => 'nullable|integer',
            'amount' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:500',
            'late_minutes' => 'nullable|integer|min:0',
        ]);

        $deductionMinutes = (int) ($validated['late_minutes'] ?? 0);
        $deductionType = null;

        if (! empty($validated['deduction_type_id'])) {
            $deductionType = DeductionType::find($validated['deduction_type_id']);
        }

        $deductionType ??= $this->getAttendanceDeductionType();
        $amount = $deductionMinutes > 0
            ? $this->calculateAttendanceDeductionAmount($attendance->user, $deductionMinutes)
            : (float) ($validated['amount'] ?? 0);

        $deduction = EmployeeDeduction::create([
            'user_id' => $attendance->user_id,
            'deduction_type_id' => $deductionType->id,
            'deduction_date' => $attendance->date,
            'amount' => round($amount, 2),
            'reason' => $validated['reason'] ?? 'Manual deduction from attendance',
            'status' => EmployeeDeduction::STATUS_APPROVED,
            'attendance_id' => $attendance->id,
            'late_minutes' => $deductionMinutes,
            'deductible_type' => Attendance::class,
            'deductible_id' => $attendance->id,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // Update attendance record with deduction minutes
        if ($deductionMinutes > 0) {
            $attendance->update([
                'deduction_minutes' => ($attendance->deduction_minutes ?? 0) + $deductionMinutes,
            ]);
        }

        return ApiResponse::success(
            new EmployeeDeductionResource($deduction->load(['user', 'deductionType'])),
            'Deduction added successfully',
            201
        );
    }

    /**
     * Mark attendance as grace day
     */
    public function markGraceDay(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->can('manage_attendance')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $attendance = Attendance::find($id);

        if (! $attendance) {
            return ApiResponse::error(__('messages.attendance_not_found'), 404);
        }

        $user = $attendance->user;

        // Check if user has grace days remaining
        if (! $user->hasGraceDaysRemaining()) {
            return ApiResponse::error('No grace days remaining for this employee', 422);
        }

        // Use a grace day
        $user->useGraceDay();
        $attendance->update(['used_grace_day' => true]);

        return ApiResponse::success(
            new AttendanceResource($attendance->fresh()->load('user')),
            'Grace day marked successfully'
        );
    }

    /**
     * Remove grace day from attendance
     */
    public function removeGraceDay(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->can('manage_attendance')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $attendance = Attendance::find($id);

        if (! $attendance) {
            return ApiResponse::error(__('messages.attendance_not_found'), 404);
        }

        if (! $attendance->used_grace_day) {
            return ApiResponse::error('This attendance record does not use a grace day', 422);
        }

        $user = $attendance->user;

        // Restore the grace day
        $user->restoreGraceDay();
        $attendance->update(['used_grace_day' => false]);

        return ApiResponse::success(
            new AttendanceResource($attendance->fresh()->load('user')),
            'Grace day removed successfully'
        );
    }

    /**
     * Create a deduction record for attendance
     */
    protected function createAttendanceDeduction(Attendance $attendance, User $user, int $deductionMinutes): EmployeeDeduction
    {
        $deductionType = $this->getAttendanceDeductionType();
        $amount = $this->calculateAttendanceDeductionAmount($user, $deductionMinutes);

        return EmployeeDeduction::create([
            'user_id' => $user->id,
            'deduction_type_id' => $deductionType->id,
            'deduction_date' => $attendance->date,
            'amount' => round($amount, 2),
            'reason' => 'Manual attendance deduction',
            'status' => EmployeeDeduction::STATUS_APPROVED,
            'attendance_id' => $attendance->id,
            'late_minutes' => $deductionMinutes,
            'deductible_type' => Attendance::class,
            'deductible_id' => $attendance->id,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    protected function getAttendanceDeductionType(): DeductionType
    {
        return DeductionType::firstOrCreate(
            ['code' => 'ATTENDANCE_DEDUCTION'],
            [
                'name' => 'Attendance Deduction',
                'description' => 'Deduction for attendance violations',
                'calculation_type' => DeductionType::CALCULATION_PER_MINUTE,
                'amount' => 0,
                'is_active' => true,
                'requires_approval' => false,
            ]
        );
    }

    protected function calculateAttendanceDeductionAmount(User $user, int $deductionMinutes): float
    {
        $hourlyRate = 0;
        if ($user->salary && $user->work_hours_per_day) {
            $hourlyRate = $user->salary / (30 * $user->work_hours_per_day);
        }

        return ($deductionMinutes / 60) * $hourlyRate;
    }

    private function forbidClientAttendance(Request $request): ?JsonResponse
    {
        if ($request->user()?->hasRole('client')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        return null;
    }
}
