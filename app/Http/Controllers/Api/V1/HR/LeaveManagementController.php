<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\HR\EmployeeLeaveResource;
use App\Http\Resources\V1\HR\LeaveTypeResource;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Policies\UserAdministrationPolicy;
use App\Services\HR\EmployeeService;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveManagementController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly UserAdministrationPolicy $administrationPolicy,
        private readonly LeaveService $leaveService,
    ) {}

    public function index(): JsonResponse
    {
        $leaveTypes = LeaveType::query()
            ->active()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(LeaveTypeResource::collection($leaveTypes));
    }

    public function indexAll(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('manage_leave_types'), 403);

        $leaveTypes = LeaveType::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(LeaveTypeResource::collection($leaveTypes));
    }

    public function storeType(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('manage_leave_types'), 403);

        $leaveType = LeaveType::create($this->leaveTypeAttributes(
            $request->validate($this->leaveTypeRules())
        ));

        return ApiResponse::success(new LeaveTypeResource($leaveType), 'Leave type created successfully', 201);
    }

    public function updateType(Request $request, LeaveType $leaveType): JsonResponse
    {
        abort_unless($request->user()?->can('manage_leave_types'), 403);

        $leaveType->update($this->leaveTypeAttributes(
            $request->validate($this->leaveTypeRules($leaveType))
        ));

        return ApiResponse::success(new LeaveTypeResource($leaveType), 'Leave type updated successfully');
    }

    public function history(Request $request, int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        if (! $request->user()->is($employee)) {
            abort_unless($request->user()?->can('manage_employees'), 403);
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:1000,9999'],
        ]);
        $year = $validated['year'] ?? today()->year;
        $leaves = EmployeeLeave::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->whereYear('start_date', $year)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
        $balanceRecords = EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');
        $leaveTypes = LeaveType::query()
            ->where(function ($query) use ($balanceRecords): void {
                $query->where('is_active', true)
                    ->orWhereIn('id', $balanceRecords->keys());
            })
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $balances = $leaveTypes->map(function (LeaveType $leaveType) use ($balanceRecords, $request): array {
            /** @var EmployeeLeaveBalance|null $balance */
            $balance = $balanceRecords->get($leaveType->id);
            $allocatedDays = (float) ($balance?->allocated_days ?? $leaveType->annual_allowance_days);
            $usedDays = (float) ($balance?->used_days ?? 0);

            return [
                'leaveType' => (new LeaveTypeResource($leaveType))->resolve($request),
                'leaveTypeId' => $leaveType->id,
                'allocatedDays' => $allocatedDays,
                'usedDays' => $usedDays,
                'remainingDays' => max(0, round($allocatedDays - $usedDays, 2)),
            ];
        })->values();

        return ApiResponse::success([
            'leaves' => EmployeeLeaveResource::collection($leaves),
            'balances' => $balances,
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        if (! $request->user()->is($employee)) {
            $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $employee);
        }

        $validated = $request->validate($this->leaveRequestRules());
        $leaveType = LeaveType::query()->active()->find($validated['leaveTypeId']);

        if (! $leaveType) {
            $this->leaveTypeUnavailable();
        }

        [$workingDays] = $this->leaveService->calculateWorkingDays(
            $validated['startDate'],
            $validated['endDate']
        );
        $this->assertPositiveWorkingDays($workingDays);

        $leave = DB::transaction(function () use ($employee, $leaveType, $validated, $workingDays): EmployeeLeave {
            User::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $leaveType = LeaveType::query()
                ->active()
                ->lockForUpdate()
                ->find($leaveType->id);

            if (! $leaveType) {
                $this->leaveTypeUnavailable();
            }

            $this->assertNoOverlap(
                $employee->id,
                $validated['startDate'],
                $validated['endDate']
            );

            return EmployeeLeave::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $validated['startDate'],
                'end_date' => $validated['endDate'],
                'working_days' => $workingDays,
                'is_paid' => $leaveType->is_paid,
                'status' => EmployeeLeave::STATUS_PENDING,
                'reason' => $validated['reason'] ?? null,
            ]);
        });

        return ApiResponse::success(
            new EmployeeLeaveResource($leave->load('leaveType')),
            'Leave request created successfully',
            201
        );
    }

    public function approve(Request $request, EmployeeLeave $leave): JsonResponse
    {
        $leave->loadMissing('employee');
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $leave->employee);
        $validated = $request->validate($this->reviewNoteRules());
        $actorId = $request->user()->id;

        $leave = DB::transaction(function () use ($leave, $validated, $actorId): EmployeeLeave {
            User::query()->whereKey($leave->employee_id)->lockForUpdate()->firstOrFail();
            $leave = EmployeeLeave::query()
                ->with('leaveType')
                ->lockForUpdate()
                ->findOrFail($leave->id);
            $this->assertPending($leave);
            $this->assertNoOverlap(
                $leave->employee_id,
                $leave->start_date->toDateString(),
                $leave->end_date->toDateString(),
                $leave->id
            );

            [$workingDays, $policy] = $this->leaveService->calculateWorkingDays(
                $leave->start_date->toDateString(),
                $leave->end_date->toDateString()
            );
            $this->assertPositiveWorkingDays($workingDays);

            /** @var LeaveType $leaveType */
            $leaveType = $leave->leaveType;
            $isPaid = $leaveType->is_paid;
            $balance = $this->balanceFor($leave, $leaveType);

            if ($isPaid) {
                $remainingDays = (float) $balance->allocated_days - (float) $balance->used_days;

                if ($remainingDays + 0.00001 < $workingDays) {
                    throw ValidationException::withMessages([
                        'workingDays' => ['The employee does not have enough paid leave balance.'],
                    ]);
                }

                $balance->update([
                    'used_days' => round((float) $balance->used_days + $workingDays, 2),
                ]);
            }

            $leave->update([
                'working_days' => $workingDays,
                'is_paid' => $isPaid,
                'status' => EmployeeLeave::STATUS_APPROVED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $validated['reviewNote'] ?? null,
                'policy_version_id' => $policy?->id,
            ]);

            return $leave->fresh(['leaveType']);
        });

        return ApiResponse::success(new EmployeeLeaveResource($leave), 'Leave request approved successfully');
    }

    public function reject(Request $request, EmployeeLeave $leave): JsonResponse
    {
        $leave->loadMissing('employee');
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $leave->employee);
        $validated = $request->validate($this->reviewNoteRules());
        $actorId = $request->user()->id;

        $leave = DB::transaction(function () use ($leave, $validated, $actorId): EmployeeLeave {
            User::query()->whereKey($leave->employee_id)->lockForUpdate()->firstOrFail();
            $leave = EmployeeLeave::query()->lockForUpdate()->findOrFail($leave->id);
            $this->assertPending($leave);

            $leave->update([
                'status' => EmployeeLeave::STATUS_REJECTED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $validated['reviewNote'] ?? null,
            ]);

            return $leave->fresh(['leaveType']);
        });

        return ApiResponse::success(new EmployeeLeaveResource($leave), 'Leave request rejected successfully');
    }

    public function cancel(Request $request, EmployeeLeave $leave): JsonResponse
    {
        $leave->loadMissing('employee');

        if (! $request->user()->is($leave->employee)) {
            $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $leave->employee);
        }

        $validated = $request->validate($this->reviewNoteRules());
        $actorId = $request->user()->id;

        $leave = DB::transaction(function () use ($leave, $validated, $actorId): EmployeeLeave {
            User::query()->whereKey($leave->employee_id)->lockForUpdate()->firstOrFail();
            $leave = EmployeeLeave::query()->lockForUpdate()->findOrFail($leave->id);

            if (! in_array($leave->status, [EmployeeLeave::STATUS_PENDING, EmployeeLeave::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending or approved leave requests can be cancelled.'],
                ]);
            }

            if ($leave->status === EmployeeLeave::STATUS_APPROVED && $leave->is_paid) {
                $balance = EmployeeLeaveBalance::query()
                    ->where('employee_id', $leave->employee_id)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', $leave->start_date->year)
                    ->lockForUpdate()
                    ->firstOrFail();
                $releasedDays = round((float) $balance->used_days - (float) $leave->working_days, 2);

                if ($releasedDays < 0) {
                    throw ValidationException::withMessages([
                        'workingDays' => ['The leave balance cannot be released.'],
                    ]);
                }

                $balance->update(['used_days' => $releasedDays]);
            }

            $leave->update([
                'status' => EmployeeLeave::STATUS_CANCELLED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $validated['reviewNote'] ?? null,
            ]);

            return $leave->fresh(['leaveType']);
        });

        return ApiResponse::success(new EmployeeLeaveResource($leave), 'Leave request cancelled successfully');
    }

    private function leaveTypeRules(?LeaveType $leaveType = null): array
    {
        $slugRule = Rule::unique('leave_types', 'slug');

        if ($leaveType) {
            $slugRule->ignore($leaveType);
        }

        $required = $leaveType ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:100'],
            'slug' => [$required, 'string', 'max:100', 'alpha_dash', $slugRule],
            'annualAllowanceDays' => [$required, 'integer', 'between:0,366'],
            'isPaid' => [$required, 'boolean'],
            'isActive' => [$required, 'boolean'],
            'displayOrder' => [$required, 'integer', 'between:0,65535'],
        ];
    }

    private function leaveTypeAttributes(array $data): array
    {
        $attributes = [];
        $fieldMap = [
            'name' => 'name',
            'slug' => 'slug',
            'annualAllowanceDays' => 'annual_allowance_days',
            'isPaid' => 'is_paid',
            'isActive' => 'is_active',
            'displayOrder' => 'display_order',
        ];

        foreach ($fieldMap as $camelCase => $snakeCase) {
            if (array_key_exists($camelCase, $data)) {
                $attributes[$snakeCase] = $data[$camelCase];
            }
        }

        return $attributes;
    }

    private function leaveRequestRules(): array
    {
        return [
            'leaveTypeId' => ['required', 'integer', 'exists:leave_types,id'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function reviewNoteRules(): array
    {
        return [
            'reviewNote' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function assertNoOverlap(int $employeeId, string $startDate, string $endDate, ?int $exceptLeaveId = null): void
    {
        $query = EmployeeLeave::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [EmployeeLeave::STATUS_PENDING, EmployeeLeave::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->lockForUpdate();

        if ($exceptLeaveId !== null) {
            $query->whereKeyNot($exceptLeaveId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'startDate' => ['The leave request overlaps an existing pending or approved leave.'],
            ]);
        }
    }

    private function assertPositiveWorkingDays(int $workingDays): void
    {
        if ($workingDays <= 0) {
            throw ValidationException::withMessages([
                'startDate' => ['The leave request must include at least one working day.'],
            ]);
        }
    }

    private function assertPending(EmployeeLeave $leave): void
    {
        if ($leave->status !== EmployeeLeave::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending leave requests can be reviewed.'],
            ]);
        }
    }

    private function leaveTypeUnavailable(): never
    {
        throw ValidationException::withMessages([
            'leaveTypeId' => ['The selected leave type is inactive or unavailable.'],
        ]);
    }

    private function balanceFor(EmployeeLeave $leave, LeaveType $leaveType): EmployeeLeaveBalance
    {
        $year = $leave->start_date->year;
        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        return $balance ?? EmployeeLeaveBalance::create([
            'employee_id' => $leave->employee_id,
            'leave_type_id' => $leaveType->id,
            'year' => $year,
            'allocated_days' => $leaveType->annual_allowance_days,
            'used_days' => 0,
        ]);
    }
}
