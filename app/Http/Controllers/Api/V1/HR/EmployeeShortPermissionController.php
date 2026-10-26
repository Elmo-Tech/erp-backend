<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\HR\EmployeeShortPermissionResource;
use App\Models\EmployeePermissionAllowance;
use App\Models\EmployeeShortPermission;
use App\Models\User;
use App\Policies\UserAdministrationPolicy;
use App\Services\HR\EmployeeService;
use App\Services\HR\HrRequestNotifier;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeShortPermissionController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly UserAdministrationPolicy $administrationPolicy,
        private readonly LeaveService $leaveService,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->hasAnyPermission(['manage_permissions', 'manage_employees', 'approve_permissions', 'reject_permissions']), 403);

        $permissions = EmployeeShortPermission::query()
            ->with(['employee.roles.permissions', 'employee.permissions'])
            ->where('status', EmployeeShortPermission::STATUS_PENDING)
            ->orderBy('permission_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->filter(fn (EmployeeShortPermission $permission): bool => $permission->employee !== null
                && ($actor->is($permission->employee) || $this->administrationPolicy->canReviewShortPermission($actor, $permission->employee)))
            ->values();

        return ApiResponse::success(['permissions' => EmployeeShortPermissionResource::collection($permissions)]);
    }

    public function history(Request $request, int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $this->administrationPolicy->authorizeHrView($request->user(), $employee, 'manage_permissions');

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $period = $validated['month'] ?? today()->format('Y-m');
        $monthStart = Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $permissions = EmployeeShortPermission::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('permission_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderByDesc('permission_date')
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->get();
        $allowance = EmployeePermissionAllowance::query()
            ->where('employee_id', $employee->id)
            ->where('period', $period)
            ->first();

        return ApiResponse::success([
            'permissions' => EmployeeShortPermissionResource::collection($permissions),
            'allowance' => $this->allowanceSummary($allowance, $period),
            'canReview' => $this->administrationPolicy->canReviewShortPermission($request->user(), $employee),
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        if (! $request->user()->is($employee)) {
            $this->administrationPolicy->authorizeHrAction($request->user(), $employee, 'manage_permissions');
        }

        $validated = $request->validate($this->requestRules());
        $registerDirectly = $this->administrationPolicy->canRegisterHrRecord($request->user(), $employee, 'manage_permissions');
        if ($registerDirectly && empty($validated['compensationType'])) {
            throw ValidationException::withMessages(['compensationType' => ['Choose Paid or Unpaid when registering a short permission.']]);
        }
        $durationMinutes = $this->durationMinutes($validated['startTime'], $validated['endTime']);

        $permission = DB::transaction(function () use ($employee, $validated, $durationMinutes, $request, $registerDirectly): EmployeeShortPermission {
            User::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $this->assertNoOverlap(
                $employee->id,
                $validated['permissionDate'],
                $validated['startTime'],
                $validated['endTime']
            );

            $permission = EmployeeShortPermission::create([
                'employee_id' => $employee->id,
                'permission_date' => $validated['permissionDate'],
                'start_time' => $validated['startTime'],
                'end_time' => $validated['endTime'],
                'duration_minutes' => $durationMinutes,
                'status' => EmployeeShortPermission::STATUS_PENDING,
                'reason' => $validated['reason'] ?? null,
            ]);

            if ($registerDirectly) {
                [$permission] = $this->approvePermission($permission, $validated['compensationType'], $request->user()->id);
            }

            return $permission;
        });

        app(HrRequestNotifier::class)->send($permission, 'permission', 'manage_permissions');

        return ApiResponse::success(
            new EmployeeShortPermissionResource($permission),
            $registerDirectly ? 'Short permission registered successfully' : 'Short permission request created successfully',
            201
        );
    }

    public function update(Request $request, EmployeeShortPermission $permission): JsonResponse
    {
        $permission->loadMissing('employee');

        if (! $request->user()->is($permission->employee)) {
            $this->administrationPolicy->authorizeHrAction($request->user(), $permission->employee, 'manage_permissions');
        }

        $validated = $request->validate($this->requestRules());

        $permission = DB::transaction(function () use ($permission, $validated): EmployeeShortPermission {
            User::query()->whereKey($permission->employee_id)->lockForUpdate()->firstOrFail();
            $permission = EmployeeShortPermission::query()->lockForUpdate()->findOrFail($permission->id);
            $this->assertPending($permission);

            $durationMinutes = $this->durationMinutes($validated['startTime'], $validated['endTime']);
            $this->assertNoOverlap(
                $permission->employee_id,
                $validated['permissionDate'],
                $validated['startTime'],
                $validated['endTime'],
                $permission->id
            );

            $permission->update([
                'permission_date' => $validated['permissionDate'],
                'start_time' => $validated['startTime'],
                'end_time' => $validated['endTime'],
                'duration_minutes' => $durationMinutes,
                'reason' => $validated['reason'] ?? null,
            ]);

            return $permission->fresh();
        });

        return ApiResponse::success(new EmployeeShortPermissionResource($permission), 'Short permission request updated successfully');
    }

    public function approve(Request $request, EmployeeShortPermission $permission): JsonResponse
    {
        $permission->loadMissing('employee');
        $this->administrationPolicy->authorizeShortPermissionReview($request->user(), $permission->employee);
        $validated = $request->validate([
            'compensationType' => ['required', 'in:paid,unpaid'],
            'reviewNote' => ['nullable', 'string', 'max:2000'],
        ]);
        $actorId = $request->user()->id;

        [$permission, $allowance] = $this->approvePermission($permission, $validated['compensationType'], $actorId, $validated['reviewNote'] ?? null);
        app(HrRequestNotifier::class)->send($permission, 'permission', 'manage_permissions');

        $period = $permission->permission_date->format('Y-m');
        $allowanceSummary = $this->allowanceSummary($allowance, $period);

        return ApiResponse::success(array_merge(
            (new EmployeeShortPermissionResource($permission))->resolve($request),
            [
                'allowance' => $allowanceSummary,
                'remainingMinutes' => $allowanceSummary['remainingMinutes'],
                'overageMinutes' => $allowanceSummary['overageMinutes'],
            ]
        ), 'Short permission request approved successfully');
    }

    private function approvePermission(EmployeeShortPermission $permission, string $compensationType, int $actorId, ?string $reviewNote = null): array
    {
        return DB::transaction(function () use ($permission, $compensationType, $reviewNote, $actorId): array {
            User::query()->whereKey($permission->employee_id)->lockForUpdate()->firstOrFail();
            $permission = EmployeeShortPermission::query()->lockForUpdate()->findOrFail($permission->id);
            $this->assertPending($permission);
            $this->assertNoOverlap(
                $permission->employee_id,
                $permission->permission_date->toDateString(),
                $permission->start_time,
                $permission->end_time,
                $permission->id
            );
            [$allocatedMinutes, $policy] = $this->leaveService->monthlyPaidPermissionMinutes();
            $isPaid = $compensationType === 'paid';
            $allowance = null;

            if ($isPaid) {
                $allowance = $this->allowanceForPaidApproval(
                    $permission->employee_id,
                    $permission->permission_date->format('Y-m'),
                    $allocatedMinutes,
                    $policy?->id
                );
                $allowance->update([
                    'used_paid_minutes' => $allowance->used_paid_minutes + $permission->duration_minutes,
                ]);
            }

            $permission->update([
                'is_paid' => $isPaid,
                'status' => EmployeeShortPermission::STATUS_APPROVED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
                'policy_version_id' => $policy?->id,
            ]);

            return [$permission->fresh(), $allowance?->fresh()];
        });
    }

    public function reject(Request $request, EmployeeShortPermission $permission): JsonResponse
    {
        $permission->loadMissing('employee');
        $this->administrationPolicy->authorizeShortPermissionReview($request->user(), $permission->employee);
        $validated = $request->validate($this->reviewNoteRules());
        $actorId = $request->user()->id;

        $permission = DB::transaction(function () use ($permission, $validated, $actorId): EmployeeShortPermission {
            User::query()->whereKey($permission->employee_id)->lockForUpdate()->firstOrFail();
            $permission = EmployeeShortPermission::query()->lockForUpdate()->findOrFail($permission->id);
            $this->assertPending($permission);

            $permission->update([
                'status' => EmployeeShortPermission::STATUS_REJECTED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $validated['reviewNote'] ?? null,
            ]);

            return $permission->fresh();
        });

        app(HrRequestNotifier::class)->send($permission, 'permission', 'manage_permissions');

        return ApiResponse::success(new EmployeeShortPermissionResource($permission), 'Short permission request rejected successfully');
    }

    public function cancel(Request $request, EmployeeShortPermission $permission): JsonResponse
    {
        $permission->loadMissing('employee');

        if (! $request->user()->is($permission->employee)) {
            $this->administrationPolicy->authorizeHrAction($request->user(), $permission->employee, 'manage_permissions');
        }

        $validated = $request->validate($this->reviewNoteRules());
        $actorId = $request->user()->id;

        $permission = DB::transaction(function () use ($permission, $validated, $actorId): EmployeeShortPermission {
            User::query()->whereKey($permission->employee_id)->lockForUpdate()->firstOrFail();
            $permission = EmployeeShortPermission::query()->lockForUpdate()->findOrFail($permission->id);

            if (! in_array($permission->status, [
                EmployeeShortPermission::STATUS_PENDING,
                EmployeeShortPermission::STATUS_APPROVED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending or approved short permission requests can be cancelled.'],
                ]);
            }

            if ($permission->status === EmployeeShortPermission::STATUS_APPROVED && $permission->is_paid) {
                $allowance = EmployeePermissionAllowance::query()
                    ->where('employee_id', $permission->employee_id)
                    ->where('period', $permission->permission_date->format('Y-m'))
                    ->lockForUpdate()
                    ->firstOrFail();
                $usedMinutes = $allowance->used_paid_minutes - $permission->duration_minutes;

                if ($usedMinutes < 0) {
                    throw ValidationException::withMessages([
                        'durationMinutes' => ['The paid permission allowance cannot be released.'],
                    ]);
                }

                $allowance->update(['used_paid_minutes' => $usedMinutes]);
            }

            $permission->update([
                'status' => EmployeeShortPermission::STATUS_CANCELLED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $validated['reviewNote'] ?? null,
            ]);

            return $permission->fresh();
        });

        return ApiResponse::success(new EmployeeShortPermissionResource($permission), 'Short permission request cancelled successfully');
    }

    private function requestRules(): array
    {
        return [
            'permissionDate' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'compensationType' => ['nullable', 'in:paid,unpaid'],
        ];
    }

    private function reviewNoteRules(): array
    {
        return [
            'reviewNote' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function durationMinutes(string $startTime, string $endTime): int
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $startTime));
        [$endHour, $endMinute] = array_map('intval', explode(':', $endTime));
        $durationMinutes = (($endHour * 60) + $endMinute) - (($startHour * 60) + $startMinute);

        if (
            $startHour > 23
            || $startMinute > 59
            || $endHour > 23
            || $endMinute > 59
            || $durationMinutes <= 0
            || $durationMinutes > 24 * 60
        ) {
            throw ValidationException::withMessages([
                'endTime' => ['The end time must be after the start time and within the same day.'],
            ]);
        }

        return $durationMinutes;
    }

    private function assertNoOverlap(
        int $employeeId,
        string $permissionDate,
        string $startTime,
        string $endTime,
        ?int $exceptPermissionId = null,
    ): void {
        $query = EmployeeShortPermission::query()
            ->where('employee_id', $employeeId)
            ->whereDate('permission_date', $permissionDate)
            ->whereIn('status', [
                EmployeeShortPermission::STATUS_PENDING,
                EmployeeShortPermission::STATUS_APPROVED,
            ])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->lockForUpdate();

        if ($exceptPermissionId !== null) {
            $query->whereKeyNot($exceptPermissionId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'startTime' => ['The short permission request overlaps an existing pending or approved request.'],
            ]);
        }
    }

    private function assertPending(EmployeeShortPermission $permission): void
    {
        if ($permission->status !== EmployeeShortPermission::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending short permission requests can be reviewed.'],
            ]);
        }
    }

    private function allowanceForPaidApproval(
        int $employeeId,
        string $period,
        int $allocatedMinutes,
        ?int $policyVersionId,
    ): EmployeePermissionAllowance {
        $allowance = EmployeePermissionAllowance::query()
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        return $allowance ?? EmployeePermissionAllowance::create([
            'employee_id' => $employeeId,
            'period' => $period,
            'allocated_minutes' => $allocatedMinutes,
            'used_paid_minutes' => 0,
            'policy_version_id' => $policyVersionId,
        ]);
    }

    private function allowanceSummary(?EmployeePermissionAllowance $allowance, string $period): array
    {
        if ($allowance === null) {
            [$allocatedMinutes] = $this->leaveService->monthlyPaidPermissionMinutes();
            $usedPaidMinutes = 0;
        } else {
            $allocatedMinutes = $allowance->allocated_minutes;
            $usedPaidMinutes = $allowance->used_paid_minutes;
        }

        return [
            'period' => $period,
            'allocatedMinutes' => $allocatedMinutes,
            'usedPaidMinutes' => $usedPaidMinutes,
            'remainingMinutes' => max(0, $allocatedMinutes - $usedPaidMinutes),
            'overageMinutes' => max(0, $usedPaidMinutes - $allocatedMinutes),
        ];
    }
}
