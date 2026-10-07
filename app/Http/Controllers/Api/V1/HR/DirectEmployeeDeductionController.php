<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\ApproveDirectEmployeeDeductionRequest;
use App\Http\Requests\V1\HR\RejectDirectEmployeeDeductionRequest;
use App\Http\Requests\V1\HR\StoreDirectEmployeeDeductionRequest;
use App\Http\Resources\V1\HR\DirectDeductionTypeResource;
use App\Http\Resources\V1\HR\DirectEmployeeDeductionResource;
use App\Models\DeductionType;
use App\Models\EmployeeDeduction;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Policies\UserAdministrationPolicy;
use App\Services\AuditService;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectEmployeeDeductionController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly UserAdministrationPolicy $administrationPolicy,
    ) {}

    public function activeTypes(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('manage_employees'), 403);

        $types = DeductionType::query()
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => DirectDeductionTypeResource::collection($types)->resolve($request),
        ]);
    }

    public function history(Request $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $employee);
        $validated = $request->validate([
            'year' => ['required', 'integer', 'digits:4'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $deductions = EmployeeDeduction::query()
            ->with(['deductionType', 'salaryAdjustment'])
            ->where('user_id', $employee->id)
            ->where('source', EmployeeDeduction::SOURCE_DIRECT_MANUAL)
            ->forMonth((int) $validated['year'], (int) $validated['month'])
            ->orderByDesc('deduction_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'deductions' => DirectEmployeeDeductionResource::collection($deductions)->resolve($request),
                'summary' => [
                    'approvedAmount' => (float) $deductions
                        ->where('status', EmployeeDeduction::STATUS_APPROVED)
                        ->sum('amount'),
                    'pendingCount' => $deductions
                        ->where('status', EmployeeDeduction::STATUS_PENDING)
                        ->count(),
                    'approvedCount' => $deductions
                        ->where('status', EmployeeDeduction::STATUS_APPROVED)
                        ->count(),
                ],
            ],
        ]);
    }

    public function store(StoreDirectEmployeeDeductionRequest $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $employee);
        $validated = $request->validated();

        $deduction = DB::transaction(function () use ($employee, $validated): EmployeeDeduction {
            User::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $deductionType = DeductionType::query()
                ->active()
                ->lockForUpdate()
                ->find($validated['deductionTypeId']);

            if (! $deductionType) {
                throw ValidationException::withMessages([
                    'deductionTypeId' => ['The selected deduction type is unavailable.'],
                ]);
            }

            return EmployeeDeduction::create([
                'user_id' => $employee->id,
                'deduction_type_id' => $deductionType->id,
                'deduction_date' => $validated['deductionDate'],
                'amount' => $validated['amount'],
                'reason' => $validated['reason'],
                'status' => EmployeeDeduction::STATUS_PENDING,
                'source' => EmployeeDeduction::SOURCE_DIRECT_MANUAL,
                // Legacy morph columns are required; the type is the only internal reference for direct entries.
                'deductible_type' => DeductionType::class,
                'deductible_id' => $deductionType->id,
            ]);
        });

        return response()->json([
            'data' => new DirectEmployeeDeductionResource($deduction->load(['deductionType', 'salaryAdjustment'])),
        ], 201);
    }

    public function approve(ApproveDirectEmployeeDeductionRequest $request, EmployeeDeduction $deduction): JsonResponse
    {
        $this->authorizeDirectDeduction($request, $deduction);
        $validated = $request->validated();
        $actorId = $request->user()->id;

        $deduction = DB::transaction(function () use ($deduction, $validated, $actorId): EmployeeDeduction {
            $deduction = EmployeeDeduction::query()->lockForUpdate()->findOrFail($deduction->id);
            $this->assertDirectPending($deduction);

            $deduction->approve($actorId);
            SalaryAdjustment::createFromDeduction($deduction, $validated['reviewNote'] ?? null);
            app(AuditService::class)->log('hr', 'direct_deduction_approved', $deduction, ['amount' => $deduction->amount, 'typeId' => $deduction->deduction_type_id], 'warning', 'Direct deduction approved');

            return $deduction->fresh(['deductionType', 'salaryAdjustment']);
        });

        return response()->json([
            'data' => new DirectEmployeeDeductionResource($deduction),
        ]);
    }

    public function reject(RejectDirectEmployeeDeductionRequest $request, EmployeeDeduction $deduction): JsonResponse
    {
        $this->authorizeDirectDeduction($request, $deduction);
        $validated = $request->validated();
        $actorId = $request->user()->id;

        $deduction = DB::transaction(function () use ($deduction, $validated, $actorId): EmployeeDeduction {
            $deduction = EmployeeDeduction::query()->lockForUpdate()->findOrFail($deduction->id);
            $this->assertDirectPending($deduction);
            $deduction->reject($actorId, $validated['reviewNote']);

            return $deduction->fresh(['deductionType', 'salaryAdjustment']);
        });

        return response()->json([
            'data' => new DirectEmployeeDeductionResource($deduction),
        ]);
    }

    public function cancel(Request $request, EmployeeDeduction $deduction): JsonResponse
    {
        abort_unless($request->user()?->can('manage_deductions') || $request->user()?->can('manage_employees'), 403);
        $this->authorizeDirectDeduction($request, $deduction);

        $deduction = DB::transaction(function () use ($deduction): EmployeeDeduction {
            $deduction = EmployeeDeduction::query()->lockForUpdate()->findOrFail($deduction->id);
            $this->assertDirectPending($deduction);
            $deduction->update([
                'status' => EmployeeDeduction::STATUS_CANCELLED,
            ]);

            return $deduction->fresh(['deductionType', 'salaryAdjustment']);
        });

        return response()->json([
            'data' => new DirectEmployeeDeductionResource($deduction),
        ]);
    }

    private function findEmployee(int $id): User
    {
        $employee = $this->employeeService->getEmployee($id);

        abort_if(! $employee, 404, 'Employee not found.');

        return $employee;
    }

    private function authorizeDirectDeduction(Request $request, EmployeeDeduction $deduction): void
    {
        $deduction->loadMissing('user');
        abort_unless($deduction->isDirectManual(), 404);
        $this->administrationPolicy->authorizeHrAction($request->user(), $deduction->user, 'manage_deductions');
    }

    private function assertDirectPending(EmployeeDeduction $deduction): void
    {
        abort_unless($deduction->isDirectManual(), 404);

        if (! $deduction->canBeApproved()) {
            throw ValidationException::withMessages([
                'status' => ['Only pending direct deductions can be reviewed.'],
            ]);
        }
    }
}
