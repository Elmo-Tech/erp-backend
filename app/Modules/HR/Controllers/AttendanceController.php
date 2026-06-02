<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\HR\Resources\AttendanceResource;
use App\Modules\HR\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    // -------------------------------------------------------------------------
    // GET /hr/attendance
    // List attendance records.
    //   - Admins (manage_attendance): can filter by user_id and date range.
    //   - Regular employees: see only their own records.
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Attendance::with('user:id,name,avatar');

        if ($user->can('manage_attendance')) {
            // Admin view — optional filters
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->integer('user_id'));
            }

            if ($request->filled('from')) {
                $query->whereDate('date', '>=', $request->input('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('date', '<=', $request->input('to'));
            }
        } else {
            // Employee view — own records only
            $query->where('user_id', $user->id);
        }

        $records = $query->orderByDesc('date')->paginate(30);

        return ApiResponse::success(
            AttendanceResource::collection($records)->response()->getData(true),
            'Attendance records retrieved successfully'
        );
    }

    // -------------------------------------------------------------------------
    // GET /hr/attendance/today
    // Returns the authenticated user's attendance record for today (if any).
    // -------------------------------------------------------------------------

    public function today(Request $request): JsonResponse
    {
        $attendance = Attendance::with('user:id,name,avatar')
            ->where('user_id', $request->user()->id)
            ->whereDate('date', Carbon::today())
            ->first();

        if ($attendance === null) {
            return ApiResponse::success(null, 'No attendance record for today');
        }

        return ApiResponse::success(
            new AttendanceResource($attendance),
            'Today\'s attendance retrieved successfully'
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'scope' => ['sometimes', 'string', 'in:week,month'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $targetUserId = $user->can('manage_attendance') && isset($validated['user_id'])
            ? (int) $validated['user_id']
            : $user->id;

        $scope = $validated['scope'] ?? 'week';
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : ($scope === 'month' ? Carbon::now()->startOfMonth() : Carbon::now()->startOfWeek());
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : ($scope === 'month' ? Carbon::now()->endOfMonth() : Carbon::now()->endOfWeek());

        $records = Attendance::with('user:id,name,avatar')
            ->where('user_id', $targetUserId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $daily = $records->map(function (Attendance $attendance): array {
            $actual = $attendance->getWorkedMinutes();
            $expected = $attendance->getExpectedWorkedMinutes();
            $span = $attendance->clock_out ? (int) $attendance->clock_in?->diffInMinutes($attendance->clock_out) : 0;

            return [
                'date' => $attendance->date?->toDateString(),
                'expectedMinutes' => $expected,
                'actualWorkedMinutes' => $actual,
                'stoppedMinutes' => max(0, $span - $actual),
                'lateMinutes' => (int) $attendance->late_minutes,
                'deductionMinutes' => (int) $attendance->deduction_minutes,
                'status' => $attendance->status,
            ];
        })->values();

        return ApiResponse::success([
            'scope' => $scope,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'userId' => $targetUserId,
            'expectedMinutes' => $daily->sum('expectedMinutes'),
            'actualWorkedMinutes' => $daily->sum('actualWorkedMinutes'),
            'stoppedMinutes' => $daily->sum('stoppedMinutes'),
            'lateMinutes' => $daily->sum('lateMinutes'),
            'deductionMinutes' => $daily->sum('deductionMinutes'),
            'daily' => $daily,
        ], 'Attendance summary retrieved successfully');
    }

    // -------------------------------------------------------------------------
    // POST /hr/attendance/check-in
    // Manual check-in for the authenticated user. Uses the same idempotent
    // attendance service that task timers use.
    // -------------------------------------------------------------------------

    public function checkIn(Request $request, AttendanceService $attendanceService): JsonResponse
    {
        $attendance = $attendanceService->checkInForToday($request->user());

        return ApiResponse::success(
            new AttendanceResource($attendance->fresh('user')),
            'Checked in successfully'
        );
    }

    // -------------------------------------------------------------------------
    // POST /hr/attendance/check-out
    // Manual check-out for the authenticated user.
    // Sets clock_out = now(), calculates total_worked_minutes.
    // -------------------------------------------------------------------------

    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var Attendance|null $attendance */
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', Carbon::today())
            ->first();

        if ($attendance === null) {
            return ApiResponse::error('No check-in record found for today.', 404);
        }

        if (! $attendance->isClockedIn()) {
            return ApiResponse::error('You have already checked out today.', 422);
        }

        $now          = Carbon::now();
        $workedMinutes = (int) $attendance->clock_in->diffInMinutes($now);

        $attendance->update([
            'clock_out'            => $now,
            'total_worked_minutes' => $workedMinutes,
        ]);

        $attendance->refresh();
        $attendance->update([
            'idle_minutes' => $attendance->calculateIdleMinutes(),
        ]);

        return ApiResponse::success(
            new AttendanceResource($attendance->fresh('user')),
            'Checked out successfully'
        );
    }

    // -------------------------------------------------------------------------
    // PATCH /hr/attendance/{attendance}   (admin only)
    // Allows admins to manually override status or add notes.
    // -------------------------------------------------------------------------

    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        abort_if(! $request->user()->can('manage_attendance'), 403, 'Unauthorized');

        $validated = $request->validate([
            'status' => ['sometimes', 'in:present,absent,late,excused'],
            'notes'  => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lateTreatment' => ['sometimes', 'string', 'in:deduct,grace,excused'],
        ]);

        if (($validated['lateTreatment'] ?? null) === 'grace') {
            $validated['used_grace_day'] = true;
            $validated['deduction_minutes'] = 0;
            unset($validated['lateTreatment']);
        }

        if (($validated['lateTreatment'] ?? null) === 'excused') {
            $validated['status'] = 'excused';
            $validated['used_grace_day'] = false;
            $validated['deduction_minutes'] = 0;
            unset($validated['lateTreatment']);
        }

        if (($validated['lateTreatment'] ?? null) === 'deduct') {
            unset($validated['lateTreatment']);
        }

        $attendance->update($validated);

        return ApiResponse::success(
            new AttendanceResource($attendance->fresh('user')),
            'Attendance record updated successfully'
        );
    }
}
