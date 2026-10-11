<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\ReviewEmployeeOvertimeRequest;
use App\Http\Requests\V1\HR\StoreEmployeeOvertimeRequest;
use App\Http\Resources\V1\HR\EmployeeOvertimeRequestResource;
use App\Models\Attendance;
use App\Models\EmployeeOvertimeRequest;
use App\Models\User;
use App\Policies\UserAdministrationPolicy;
use App\Services\AuditService;
use App\Services\HR\EmployeeService;
use App\Services\HR\OvertimeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeOvertimeRequestController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly UserAdministrationPolicy $administrationPolicy,
        private readonly OvertimeRequestService $overtimeRequestService,
    ) {}

    public function history(HttpRequest $httpRequest, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->authorizeEmployeeAccess($httpRequest->user(), $employee);
        $validated = $httpRequest->validate([
            'year' => ['required', 'integer', 'digits:4'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $overtimeRequests = EmployeeOvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->whereYear('overtime_date', (int) $validated['year'])
            ->whereMonth('overtime_date', (int) $validated['month'])
            ->orderByDesc('overtime_date')
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->get();

        // Attendance-derived extra time for days without any approved request.
        // Approved requests already carry their own actual/approved split via
        // synchronize(); this covers the "worked late but never filed" case so
        // managers see the full unapproved picture without double counting.
        $approvedDates = $overtimeRequests
            ->where('status', EmployeeOvertimeRequest::STATUS_APPROVED)
            ->map(fn (EmployeeOvertimeRequest $r) => $r->overtime_date->toDateString())
            ->unique()
            ->flip();
        $attendances = Attendance::query()
            ->where('user_id', $employee->id)
            ->whereYear('date', (int) $validated['year'])
            ->whereMonth('date', (int) $validated['month'])
            ->whereNotNull('clock_out')
            ->get();
        $unrequestedDays = [];
        $unrequestedMinutes = 0;

        foreach ($attendances as $attendance) {
            $dateString = $attendance->date->toDateString();

            if ($approvedDates->has($dateString)) {
                continue;
            }

            $expected = $attendance->expected_clock_out ?: $employee->expected_clock_out;

            if (! is_string($expected) || $expected === '' || $attendance->clock_out === null) {
                continue;
            }

            $expectedAt = Carbon::parse($dateString.' '.substr($expected, 0, 5));
            $clockOut = $attendance->clock_out;

            if (! $clockOut->greaterThan($expectedAt)) {
                continue;
            }

            $extra = (int) $expectedAt->diffInMinutes($clockOut);

            if ($extra <= 0) {
                continue;
            }

            $unrequestedMinutes += $extra;
            $unrequestedDays[] = ['date' => $dateString, 'extraMinutes' => $extra];
        }

        $requestUnapproved = (int) $overtimeRequests->sum(
            fn (EmployeeOvertimeRequest $request): int => $request->unapproved_minutes ?? 0
        );

        return response()->json([
            'data' => [
                'requests' => EmployeeOvertimeRequestResource::collection($overtimeRequests)->resolve($httpRequest),
                'summary' => [
                    'requestedMinutes' => (int) $overtimeRequests->sum('requested_minutes'),
                    'actualMinutes' => (int) $overtimeRequests->sum('actual_minutes') + $unrequestedMinutes,
                    'approvedMinutes' => (int) $overtimeRequests->sum('approved_minutes'),
                    'unapprovedMinutes' => $requestUnapproved + $unrequestedMinutes,
                    'unrequestedMinutes' => $unrequestedMinutes,
                    'unrequestedDays' => $unrequestedDays,
                    'unrequestedDaysCount' => count($unrequestedDays),
                    'payableAmount' => (float) $overtimeRequests->sum('payable_amount'),
                    'pendingCount' => $overtimeRequests
                        ->where('status', EmployeeOvertimeRequest::STATUS_PENDING)
                        ->count(),
                ],
            ],
        ]);
    }

    public function store(StoreEmployeeOvertimeRequest $httpRequest, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->authorizeEmployeeAccess($httpRequest->user(), $employee);
        $validated = $httpRequest->validated();
        $this->overtimeRequestService->assertSubmissionAllowed(
            $employee,
            $validated['overtimeDate'],
            $validated['startTime'],
            $validated['endTime'],
        );
        $requestedMinutes = $this->overtimeRequestService->durationMinutes(
            $validated['startTime'],
            $validated['endTime'],
        );

        $overtimeRequest = DB::transaction(function () use ($employee, $validated, $requestedMinutes): EmployeeOvertimeRequest {
            User::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            return EmployeeOvertimeRequest::create([
                'employee_id' => $employee->id,
                'overtime_date' => $validated['overtimeDate'],
                'start_time' => $validated['startTime'],
                'end_time' => $validated['endTime'],
                'requested_minutes' => $requestedMinutes,
                'status' => EmployeeOvertimeRequest::STATUS_PENDING,
                'reason' => $validated['reason'],
            ]);
        });

        return response()->json([
            'data' => new EmployeeOvertimeRequestResource($overtimeRequest),
        ], 201);
    }

    public function approve(
        ReviewEmployeeOvertimeRequest $httpRequest,
        EmployeeOvertimeRequest $request,
    ): JsonResponse {
        $request->loadMissing('employee');
        $this->administrationPolicy->authorizeHrAction($httpRequest->user(), $request->employee, 'manage_overtime');

        $overtimeRequest = $this->overtimeRequestService->approve(
            $request,
            $httpRequest->user(),
            $httpRequest->validated()['reviewNote'] ?? null,
        );
        app(AuditService::class)->log('hr', 'overtime_approved', $overtimeRequest, ['hourlyRate' => $overtimeRequest->hourly_rate, 'multiplier' => $overtimeRequest->overtime_multiplier], 'info', 'Overtime approved for '.$overtimeRequest->overtime_date?->toDateString());

        return response()->json([
            'data' => new EmployeeOvertimeRequestResource($overtimeRequest),
        ]);
    }

    public function reject(
        ReviewEmployeeOvertimeRequest $httpRequest,
        EmployeeOvertimeRequest $request,
    ): JsonResponse {
        $request->loadMissing('employee');
        $this->administrationPolicy->authorizeHrAction($httpRequest->user(), $request->employee, 'manage_overtime');

        $overtimeRequest = $this->overtimeRequestService->reject(
            $request,
            $httpRequest->user(),
            $httpRequest->validated()['reviewNote'] ?? null,
        );

        return response()->json([
            'data' => new EmployeeOvertimeRequestResource($overtimeRequest),
        ]);
    }

    public function cancel(HttpRequest $httpRequest, EmployeeOvertimeRequest $request): JsonResponse
    {
        $request->loadMissing('employee');
        $this->authorizeEmployeeAccess($httpRequest->user(), $request->employee);
        $validated = $httpRequest->validate([
            'reviewNote' => ['nullable', 'string', 'max:2000'],
        ]);

        $overtimeRequest = $this->overtimeRequestService->cancel(
            $request,
            $httpRequest->user(),
            $validated['reviewNote'] ?? null,
        );

        return response()->json([
            'data' => new EmployeeOvertimeRequestResource($overtimeRequest),
        ]);
    }

    private function findEmployee(int $id): User
    {
        $employee = $this->employeeService->getEmployee($id);

        abort_if($employee === null, 404, 'Employee not found.');

        return $employee;
    }

    private function authorizeEmployeeAccess(User $actor, User $employee): void
    {
        if (! $actor->is($employee)) {
            $this->administrationPolicy->authorizeHrAction($actor, $employee, 'manage_overtime');
        }
    }
}
