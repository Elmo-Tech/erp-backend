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
use App\Services\HR\SalaryRateCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function destroy(Request $request, EmploymentContract $contract): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($contract->employee_id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $this->administrationPolicy->authorizeHrAction($request->user(), $employee, 'manage_employee_contracts');

        if ($contract->status !== EmploymentContract::STATUS_DRAFT) {
            return ApiResponse::error('Only draft contracts can be deleted. Terminate or expire active history instead.', 422);
        }

        DB::transaction(function () use ($contract): void {
            $contract = EmploymentContract::query()->lockForUpdate()->findOrFail($contract->id);

            if ($contract->hasFile()) {
                Storage::disk($contract->disk ?? 'local')->delete($contract->file_path);
            }

            app(AuditService::class)->log('hr', 'contract_deleted', $contract, ['status' => $contract->status], 'warning', 'Draft employment contract deleted');
            $contract->delete();
        });

        return ApiResponse::success(null, __('messages.contracts.deleted'));
    }

    public function uploadFile(Request $request, EmploymentContract $contract): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($contract->employee_id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $this->administrationPolicy->authorizeHrAction($request->user(), $employee, 'manage_employee_contracts');

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv', 'max:10240'],
        ]);

        $file = $validated['file'];
        $path = $file->storeAs("employment-contracts/{$contract->id}", (string) Str::uuid(), 'local');

        if (! is_string($path)) {
            throw new \RuntimeException('Unable to store contract file.');
        }

        try {
            $contract = DB::transaction(function () use ($contract, $file, $path, $request): EmploymentContract {
                $contract = EmploymentContract::query()->lockForUpdate()->findOrFail($contract->id);

                if ($contract->hasFile()) {
                    Storage::disk($contract->disk ?? 'local')->delete($contract->file_path);
                }

                $contract->update([
                    'disk' => 'local',
                    'file_path' => $path,
                    'original_filename' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 255, ''),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'file_uploaded_by' => $request->user()->id,
                    'file_uploaded_at' => now(),
                ]);

                return $contract->fresh();
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        app(AuditService::class)->log('hr', 'contract_file_uploaded', $contract, ['fileName' => $contract->original_filename], 'info', 'Contract file uploaded');

        return ApiResponse::success(new EmploymentContractResource($contract), 'Contract file uploaded successfully', 201);
    }

    public function downloadFile(Request $request, EmploymentContract $contract): StreamedResponse|JsonResponse
    {
        $employee = $this->employeeService->getEmployee($contract->employee_id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        if (! $request->user()->is($employee)) {
            $this->administrationPolicy->authorizeHrAction($request->user(), $employee, 'manage_employee_contracts');
        }

        if (! $contract->hasFile() || ! Storage::disk($contract->disk ?? 'local')->exists($contract->file_path)) {
            return ApiResponse::error('Contract file not found', 404);
        }

        return Storage::disk($contract->disk ?? 'local')->download(
            $contract->file_path,
            $contract->original_filename ?? 'contract',
            ['Content-Type' => $contract->mime_type ?? 'application/octet-stream']
        );
    }

    public function deleteFile(Request $request, EmploymentContract $contract): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($contract->employee_id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $this->administrationPolicy->authorizeHrAction($request->user(), $employee, 'manage_employee_contracts');

        DB::transaction(function () use ($contract): void {
            $contract = EmploymentContract::query()->lockForUpdate()->findOrFail($contract->id);

            if ($contract->hasFile()) {
                Storage::disk($contract->disk ?? 'local')->delete($contract->file_path);
            }

            $contract->update([
                'disk' => null,
                'file_path' => null,
                'original_filename' => null,
                'mime_type' => null,
                'file_size' => null,
                'file_uploaded_by' => null,
                'file_uploaded_at' => null,
            ]);
        });

        app(AuditService::class)->log('hr', 'contract_file_deleted', $contract, [], 'warning', 'Contract file deleted');

        return ApiResponse::success(new EmploymentContractResource($contract->fresh()), 'Contract file deleted successfully');
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

        $employee->update([
            'salary' => $contract->base_salary,
            'hourly_rate' => $contract->hourly_rate,
            'work_hours_per_day' => $contract->work_hours_per_day,
            'work_start_time' => $contract->work_start_time,
            'internal_hourly_cost' => app(SalaryRateCalculator::class)->hourlyRate(
                (float) $contract->base_salary,
                (float) $contract->work_hours_per_day,
            ),
        ]);
    }

    private function contractAttributes(array $data, ?EmploymentContract $contract = null): array
    {
        $startDate = $data['startDate'] ?? $contract?->start_date?->toDateString();
        $durationMonths = $data['durationMonths'] ?? $contract?->duration_months;

        return [
            'employment_type' => $data['employmentType'] ?? $contract?->employment_type,
            'base_salary' => $data['baseSalary'] ?? $contract?->base_salary,
            'hourly_rate' => array_key_exists('hourlyRate', $data) ? $data['hourlyRate'] : $contract?->hourly_rate,
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
