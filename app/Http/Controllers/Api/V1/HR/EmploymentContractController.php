<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\StoreEmploymentContractRequest;
use App\Http\Requests\V1\HR\UpdateEmploymentContractRequest;
use App\Http\Resources\V1\HR\EmploymentContractResource;
use App\Models\EmploymentContract;
use App\Models\User;
use App\Policies\UserAdministrationPolicy;
use App\Services\AuditService;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmploymentContractController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly UserAdministrationPolicy $administrationPolicy
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('manage_employee_contracts') || $request->user()?->can('manage_employees'), 403);

        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $contracts = $employee->employmentContracts()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(EmploymentContractResource::collection($contracts));
    }

    public function store(StoreEmploymentContractRequest $request, int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $this->administrationPolicy->authorizeHrAction($request->user(), $employee, 'manage_employee_contracts');
        $validated = $request->validated();

        $contract = DB::transaction(function () use ($employee, $validated, $request): EmploymentContract {
            User::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            $contract = EmploymentContract::create(array_merge(
                ['employee_id' => $employee->id],
                $this->contractAttributes($validated)
            ));

            $this->supersedeOtherActiveContracts($contract, $request->user()->id);
            $this->syncEmployeeSalary($contract, $employee);
            app(AuditService::class)->log('hr', 'contract_created', $contract, ['status' => $contract->status, 'baseSalary' => $contract->base_salary, 'durationMonths' => $contract->duration_months], 'info', 'Employment contract created');

            return $contract;
        });

        return ApiResponse::success(new EmploymentContractResource($contract), 'Employment contract created successfully', 201);
    }

    public function update(UpdateEmploymentContractRequest $request, EmploymentContract $contract): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($contract->employee_id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $this->administrationPolicy->authorizeHrAction($request->user(), $employee, 'manage_employee_contracts');
        $validated = $request->validated();

        $contract = DB::transaction(function () use ($contract, $employee, $validated, $request): EmploymentContract {
            User::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            $contract = EmploymentContract::query()->lockForUpdate()->findOrFail($contract->id);
            $contract->update($this->contractAttributes($validated, $contract));

            $this->supersedeOtherActiveContracts($contract, $request->user()->id);
            $this->syncEmployeeSalary($contract->fresh(), $employee);
            app(AuditService::class)->log('hr', 'contract_updated', $contract, ['status' => $contract->status, 'baseSalary' => $contract->base_salary], 'info', 'Employment contract updated');

            return $contract->fresh();
        });

        return ApiResponse::success(new EmploymentContractResource($contract), 'Employment contract updated successfully');
    }

    /**
     * The employment contract is the single source of truth for base salary.
     * Mirror its base_salary onto the employee record so overtime, late
     * deductions, advances, and partial pay never drift apart.
     */
    private function syncEmployeeSalary(EmploymentContract $contract, User $employee): void
    {
        if ($contract->status !== EmploymentContract::STATUS_ACTIVE) {
            return;
        }

        User::query()->whereKey($employee->id)->update(['salary' => $contract->base_salary]);
    }

    private function contractAttributes(array $data, ?EmploymentContract $contract = null): array
    {
        $startDate = $data['startDate'] ?? $contract?->start_date?->toDateString();
        $durationMonths = $data['durationMonths'] ?? $contract?->duration_months;

        return [
            'employment_type' => $data['employmentType'] ?? $contract?->employment_type,
            'base_salary' => $data['baseSalary'] ?? $contract?->base_salary,
            'start_date' => $startDate,
            'end_date' => Carbon::parse($startDate)
                ->addMonthsNoOverflow($durationMonths)
                ->subDay()
                ->toDateString(),
            'terminated_on' => $data['status'] === EmploymentContract::STATUS_TERMINATED
                ? ($data['terminatedOn'] ?? $contract?->terminated_on?->toDateString())
                : null,
            'duration_months' => $durationMonths,
            'work_days' => $data['workDays'] ?? $contract?->work_days,
            'work_hours_per_day' => $data['workHoursPerDay'] ?? $contract?->work_hours_per_day,
            'work_start_time' => array_key_exists('workStartTime', $data)
                ? $data['workStartTime'].':00'
                : $contract?->work_start_time,
            'status' => $data['status'],
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $contract?->notes,
        ];
    }

    private function supersedeOtherActiveContracts(EmploymentContract $contract, int $actorId): void
    {
        if ($contract->status !== EmploymentContract::STATUS_ACTIVE) {
            return;
        }

        EmploymentContract::query()
            ->where('employee_id', $contract->employee_id)
            ->where('status', EmploymentContract::STATUS_ACTIVE)
            ->whereKeyNot($contract->id)
            ->update([
                'status' => EmploymentContract::STATUS_SUPERSEDED,
                'updated_by' => $actorId,
            ]);
    }
}
