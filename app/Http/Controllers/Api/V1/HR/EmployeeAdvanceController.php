<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\ApproveEmployeeAdvanceRequest;
use App\Http\Requests\V1\HR\DisburseEmployeeAdvanceRequest;
use App\Http\Requests\V1\HR\RejectEmployeeAdvanceRequest;
use App\Http\Requests\V1\HR\SettleEmployeeAdvanceSettlementRequest;
use App\Http\Requests\V1\HR\StoreEmployeeAdvanceRequest;
use App\Http\Resources\V1\HR\EmployeeAdvanceResource;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceSettlement;
use App\Models\User;
use App\Policies\UserAdministrationPolicy;
use App\Services\AuditService;
use App\Services\HR\EmployeeAdvanceService;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeAdvanceController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly UserAdministrationPolicy $administrationPolicy,
        private readonly EmployeeAdvanceService $employeeAdvanceService,
    ) {}

    public function history(Request $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->authorizeEmployeeAccess($request->user(), $employee);
        $validated = $request->validate([
            'year' => ['required', 'integer', 'digits:4'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        // Advance history is grouped by its request creation month, not a later review or disbursement date.
        $advances = EmployeeAdvance::query()
            ->with('settlements')
            ->where('employee_id', $employee->id)
            ->whereYear('created_at', (int) $validated['year'])
            ->whereMonth('created_at', (int) $validated['month'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'advances' => EmployeeAdvanceResource::collection($advances)->resolve($request),
                'summary' => [
                    'requestedAmount' => (float) $advances->sum('requested_amount'),
                    'approvedAmount' => (float) $advances->sum('approved_amount'),
                    'disbursedAmount' => (float) $advances->sum('disbursed_amount'),
                    'outstandingAmount' => (float) $advances->sum(
                        fn (EmployeeAdvance $advance): float => $advance->outstanding_amount
                    ),
                    'pendingCount' => $advances->where('status', EmployeeAdvance::STATUS_PENDING)->count(),
                    'scheduledSettlementAmount' => (float) $advances
                        ->flatMap(fn (EmployeeAdvance $advance) => $advance->settlements)
                        ->where('status', EmployeeAdvanceSettlement::STATUS_SCHEDULED)
                        ->sum('scheduled_amount'),
                ],
            ],
        ]);
    }

    public function store(StoreEmployeeAdvanceRequest $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->authorizeEmployeeAccess($request->user(), $employee);
        $advance = $this->employeeAdvanceService->create($employee, $request->validated());

        return response()->json([
            'data' => new EmployeeAdvanceResource($advance->load('settlements')),
        ], 201);
    }

    public function show(Request $request, EmployeeAdvance $advance): JsonResponse
    {
        $advance->loadMissing('employee', 'settlements');
        $this->authorizeEmployeeAccess($request->user(), $advance->employee);

        return response()->json([
            'data' => new EmployeeAdvanceResource($advance),
        ]);
    }

    public function approve(ApproveEmployeeAdvanceRequest $request, EmployeeAdvance $advance): JsonResponse
    {
        $advance->loadMissing('employee');
        $this->authorizeManager($request->user(), $advance);
        abort_if($advance->created_by === $request->user()->id, 403);
        $validated = $request->validated();
        $advance = $this->employeeAdvanceService->approve(
            $advance,
            $request->user(),
            $validated['approvedAmount'] ?? null,
            $validated['reviewNote'] ?? null,
        );
        app(AuditService::class)->log('hr', 'advance_approved', $advance, ['approvedAmount' => $advance->approved_amount], 'warning', 'Advance approved');

        return response()->json([
            'data' => new EmployeeAdvanceResource($advance),
        ]);
    }

    public function reject(RejectEmployeeAdvanceRequest $request, EmployeeAdvance $advance): JsonResponse
    {
        $advance->loadMissing('employee');
        $this->authorizeManager($request->user(), $advance);
        abort_if($advance->created_by === $request->user()->id, 403);
        $advance = $this->employeeAdvanceService->reject(
            $advance,
            $request->user(),
            $request->validated('reviewNote'),
        );

        return response()->json([
            'data' => new EmployeeAdvanceResource($advance),
        ]);
    }

    public function cancel(Request $request, EmployeeAdvance $advance): JsonResponse
    {
        $advance->loadMissing('employee');
        $this->authorizeEmployeeAccess($request->user(), $advance->employee);
        $validated = $request->validate([
            'reviewNote' => ['nullable', 'string', 'max:2000'],
        ]);
        $advance = $this->employeeAdvanceService->cancel(
            $advance,
            $request->user(),
            $validated['reviewNote'] ?? null,
        );

        return response()->json([
            'data' => new EmployeeAdvanceResource($advance),
        ]);
    }

    public function disburse(DisburseEmployeeAdvanceRequest $request, EmployeeAdvance $advance): JsonResponse
    {
        $advance->loadMissing('employee');
        $this->authorizeManager($request->user(), $advance);
        $advance = $this->employeeAdvanceService->disburse(
            $advance,
            $request->user(),
            $request->validated('disbursementReference'),
        );
        app(AuditService::class)->log('hr', 'advance_disbursed', $advance, ['amount' => $advance->disbursed_amount], 'warning', 'Advance disbursed');

        return response()->json([
            'data' => new EmployeeAdvanceResource($advance),
        ]);
    }

    public function settle(
        SettleEmployeeAdvanceSettlementRequest $request,
        EmployeeAdvanceSettlement $settlement,
    ): JsonResponse {
        $settlement->loadMissing('advance.employee');
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $settlement->advance->employee);
        $settlement = $this->employeeAdvanceService->settle(
            $settlement,
            $request->user(),
            $request->validated('settlementReference'),
        );

        return response()->json([
            'data' => new EmployeeAdvanceResource($settlement->advance),
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
            $this->administrationPolicy->authorizeEmployeeMutation($actor, $employee);
        }
    }

    private function authorizeManager(User $actor, EmployeeAdvance $advance): void
    {
        $this->administrationPolicy->authorizeEmployeeMutation($actor, $advance->employee);
    }
}
