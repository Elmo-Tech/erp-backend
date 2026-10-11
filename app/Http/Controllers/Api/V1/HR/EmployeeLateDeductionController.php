<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\EvaluateLateDeductionsRequest;
use App\Http\Requests\V1\HR\OverrideEmployeeLateDeductionRequest;
use App\Http\Resources\V1\HR\EmployeeLateDeductionResource;
use App\Models\EmployeeLateDeduction;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Policies\UserAdministrationPolicy;
use App\Services\AuditService;
use App\Services\HR\EmployeeService;
use App\Services\HR\LateDeductionEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeLateDeductionController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly UserAdministrationPolicy $administrationPolicy,
        private readonly LateDeductionEvaluator $lateDeductionEvaluator,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->administrationPolicy->authorizeHrView($request->user(), $employee, 'manage_deductions');
        $validated = $this->periodRules($request);

        return $this->recordsResponse($employee, (int) $validated['year'], (int) $validated['month'], $request);
    }

    public function evaluate(EvaluateLateDeductionsRequest $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->administrationPolicy->authorizeHrAction($request->user(), $employee, 'manage_deductions');
        $validated = $request->validated();
        $this->lateDeductionEvaluator->evaluate(
            $employee,
            (int) $validated['year'],
            (int) $validated['month'],
            $request->user()->id,
        );

        return $this->recordsResponse($employee, (int) $validated['year'], (int) $validated['month'], $request);
    }

    public function override(
        OverrideEmployeeLateDeductionRequest $request,
        EmployeeLateDeduction $record,
    ): JsonResponse {
        $record->loadMissing('employee');
        $this->administrationPolicy->authorizeHrAction($request->user(), $record->employee, 'manage_deductions');
        $validated = $request->validated();
        $amount = number_format((float) $validated['amount'], 2, '.', '');
        $reason = $validated['reason'];

        $record = DB::transaction(function () use ($record, $amount, $reason): EmployeeLateDeduction {
            $record = EmployeeLateDeduction::query()->lockForUpdate()->findOrFail($record->id);

            if ($record->status === EmployeeLateDeduction::STATUS_WAIVED) {
                throw ValidationException::withMessages([
                    'status' => ['Waived late deductions cannot be overridden.'],
                ]);
            }

            $adjustment = SalaryAdjustment::query()->lockForUpdate()->find($record->salary_adjustment_id);

            if ($adjustment === null) {
                throw ValidationException::withMessages([
                    'salaryAdjustmentId' => ['The late deduction has no salary adjustment to override.'],
                ]);
            }

            if ($adjustment->status === SalaryAdjustment::STATUS_PROCESSED) {
                throw ValidationException::withMessages([
                    'status' => ['Processed salary adjustments cannot be overridden.'],
                ]);
            }

            if ((float) $amount === 0.0) {
                $adjustment->update([
                    'amount' => $amount,
                    'reason' => $reason,
                ]);

                if ($adjustment->status !== SalaryAdjustment::STATUS_CANCELLED) {
                    $adjustment->cancel($reason);
                }
            } else {
                if ($adjustment->status !== SalaryAdjustment::STATUS_APPROVED) {
                    throw ValidationException::withMessages([
                        'status' => ['Only approved, unprocessed salary adjustments can be overridden.'],
                    ]);
                }

                $adjustment->update([
                    'amount' => $amount,
                    'reason' => $reason,
                ]);
            }

            // A zero override remains an override so its original eligible occurrence is preserved.
            $record->update([
                'override_amount' => $amount,
                'override_reason' => $reason,
                'status' => EmployeeLateDeduction::STATUS_OVERRIDDEN,
            ]);
            app(AuditService::class)->log('hr', 'late_deduction_overridden', $record, ['amount' => $amount, 'reason' => $reason], 'warning', 'Late deduction overridden');

            return $record->fresh('salaryAdjustment');
        });

        return response()->json([
            'data' => new EmployeeLateDeductionResource($record),
        ]);
    }

    /**
     * Client decision 3: monthly auto-reset plus manual reset by Admin.
     * Manual reset waives unprocessed records for the month (audit-preserved)
     * and cancels their linked salary adjustments so counters restart.
     */
    public function reset(Request $request, int $id): JsonResponse
    {
        $employee = $this->findEmployee($id);
        $this->administrationPolicy->authorizeHrAction($request->user(), $employee, 'manage_deductions');
        $validated = $request->validate([
            'year' => ['required', 'integer', 'digits:4'],
            'month' => ['required', 'integer', 'between:1,12'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $reason = $validated['reason'] ?? 'Manual monthly reset by Admin.';
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        DB::transaction(function () use ($employee, $monthStart, $monthEnd, $reason): void {
            $records = EmployeeLateDeduction::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->lockForUpdate()
                ->get();

            foreach ($records as $record) {
                if ($record->status === EmployeeLateDeduction::STATUS_WAIVED) {
                    continue;
                }
                app(AuditService::class)->log('hr', 'late_deduction_reset_waived', $record, ['reason' => $reason], 'warning', 'Late deduction waived on monthly reset');

                if ($record->salary_adjustment_id !== null) {
                    $adjustment = SalaryAdjustment::query()
                        ->lockForUpdate()
                        ->find($record->salary_adjustment_id);

                    if ($adjustment !== null && $adjustment->status === SalaryAdjustment::STATUS_PROCESSED) {
                        throw ValidationException::withMessages([
                            'status' => ['Processed payroll records cannot be reset.'],
                        ]);
                    }

                    if ($adjustment !== null && $adjustment->status !== SalaryAdjustment::STATUS_CANCELLED) {
                        $adjustment->cancel($reason);
                    }
                }

                $record->update([
                    'status' => EmployeeLateDeduction::STATUS_WAIVED,
                    'waiver_reason' => $reason,
                    'override_amount' => 0,
                    'override_reason' => $reason,
                ]);
            }
        });

        return $this->recordsResponse($employee, $year, $month, $request);
    }

    private function findEmployee(int $id): User
    {
        $employee = $this->employeeService->getEmployee($id);

        abort_if(! $employee, 404, 'Employee not found.');

        return $employee;
    }

    private function periodRules(Request $request): array
    {
        return $request->validate([
            'year' => ['required', 'integer', 'digits:4'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);
    }

    private function recordsResponse(User $employee, int $year, int $month, Request $request): JsonResponse
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $records = $this->lateDeductionEvaluator->recordsForPeriod(
            $employee->id,
            $monthStart,
            $monthStart->copy()->endOfMonth(),
        );

        return response()->json([
            'data' => [
                'records' => EmployeeLateDeductionResource::collection($records)->resolve($request),
                'summary' => [
                    'calculatedAmount' => (float) round($records->sum('calculated_amount'), 2),
                    'adjustedAmount' => (float) round($records->sum(
                        fn (EmployeeLateDeduction $record): float => $record->override_amount !== null
                            ? (float) $record->override_amount
                            : (float) $record->calculated_amount
                    ), 2),
                    'waivedCount' => $records->where('status', EmployeeLateDeduction::STATUS_WAIVED)->count(),
                    'evaluatedCount' => $records->count(),
                ],
            ],
        ]);
    }
}
