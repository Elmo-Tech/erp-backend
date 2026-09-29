<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\CalculateEmployeePartialPayRequest;
use App\Http\Requests\V1\HR\ReviewEmployeePartialPayRequest;
use App\Http\Resources\V1\HR\EmployeePartialPayResource;
use App\Models\EmployeePartialPay;
use App\Models\User;
use App\Policies\UserAdministrationPolicy;
use App\Services\HR\EmployeePartialPayService;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeePartialPayController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly UserAdministrationPolicy $administrationPolicy,
        private readonly EmployeePartialPayService $employeePartialPayService,
    ) {}

    public function history(Request $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->authorizeEmployeeAccess($request->user(), $employee);
        $validated = $this->periodRules($request);

        $records = EmployeePartialPay::query()
            ->with('salaryAdjustment')
            ->where('employee_id', $employee->id)
            ->where('payroll_year', $validated['year'])
            ->where('payroll_month', $validated['month'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'records' => EmployeePartialPayResource::collection($records)->resolve($request),
                'summary' => [
                    'grossAmount' => (float) $records->sum('gross_amount'),
                    'approvedGrossAmount' => (float) $records
                        ->where('status', EmployeePartialPay::STATUS_APPROVED)
                        ->sum('gross_amount'),
                    'draftCount' => $records->where('status', EmployeePartialPay::STATUS_DRAFT)->count(),
                    'approvedCount' => $records->where('status', EmployeePartialPay::STATUS_APPROVED)->count(),
                    'cancelledCount' => $records->where('status', EmployeePartialPay::STATUS_CANCELLED)->count(),
                ],
            ],
        ]);
    }

    public function preview(CalculateEmployeePartialPayRequest $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $employee);
        $calculation = $this->employeePartialPayService->preview($employee, $request->validated());

        return response()->json([
            'data' => (new EmployeePartialPayResource(new EmployeePartialPay($calculation)))->resolve($request),
        ]);
    }

    public function store(CalculateEmployeePartialPayRequest $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $employee);
        $partialPay = $this->employeePartialPayService->create($employee, $request->validated());

        return response()->json([
            'data' => new EmployeePartialPayResource($partialPay->load('salaryAdjustment')),
        ], 201);
    }

    public function approve(
        ReviewEmployeePartialPayRequest $request,
        EmployeePartialPay $record,
    ): JsonResponse {
        $record->loadMissing('employee');
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $record->employee);
        abort_if($record->created_by === $request->user()->id, 403);
        $partialPay = $this->employeePartialPayService->approve(
            $record,
            $request->user(),
            $request->validated('reviewNote'),
        );

        return response()->json([
            'data' => new EmployeePartialPayResource($partialPay),
        ]);
    }

    public function cancel(
        ReviewEmployeePartialPayRequest $request,
        EmployeePartialPay $record,
    ): JsonResponse {
        $record->loadMissing('employee');
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $record->employee);
        $partialPay = $this->employeePartialPayService->cancel(
            $record,
            $request->validated('reviewNote'),
        );

        return response()->json([
            'data' => new EmployeePartialPayResource($partialPay),
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

    private function periodRules(Request $request): array
    {
        return $request->validate([
            'year' => ['required', 'integer', 'digits:4'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);
    }
}
