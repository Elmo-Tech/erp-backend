<?php

namespace App\Services\HR;

use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceSettlement;
use App\Models\EmployeePartialPay;
use App\Models\EmploymentContract;
use App\Models\HrPolicy;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeePartialPayService
{
    private const DEFAULT_DAILY_RATE_DIVISOR = 30;

    private const DEFAULT_MID_MONTH_HIRE_PAID_DAYS = 15;

    public function preview(User $employee, array $attributes): array
    {
        return $this->calculate($employee, $attributes);
    }

    public function create(User $employee, array $attributes): EmployeePartialPay
    {
        return DB::transaction(function () use ($employee, $attributes): EmployeePartialPay {
            $employee = User::query()->lockForUpdate()->findOrFail($employee->id);
            $calculation = $this->calculate($employee, $attributes, true);

            $existing = EmployeePartialPay::query()
                ->where('employee_id', $employee->id)
                ->where('payroll_year', $calculation['payroll_year'])
                ->where('payroll_month', $calculation['payroll_month'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'payrollMonth' => ['A partial pay record already exists for this employee and payroll month.'],
                ]);
            }

            return EmployeePartialPay::create($calculation);
        });
    }

    public function approve(
        EmployeePartialPay $partialPay,
        User $reviewer,
        ?string $reviewNote,
    ): EmployeePartialPay {
        return DB::transaction(function () use ($partialPay, $reviewer, $reviewNote): EmployeePartialPay {
            $partialPay = EmployeePartialPay::query()->lockForUpdate()->findOrFail($partialPay->id);
            User::query()->whereKey($partialPay->employee_id)->lockForUpdate()->firstOrFail();

            if ($partialPay->status === EmployeePartialPay::STATUS_APPROVED) {
                return $partialPay->fresh('salaryAdjustment');
            }

            if ($partialPay->status !== EmployeePartialPay::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft employee partial pays can be approved.'],
                ]);
            }

            $existingAdjustment = SalaryAdjustment::query()
                ->where('reference_type', EmployeePartialPay::class)
                ->where('reference_id', $partialPay->id)
                ->lockForUpdate()
                ->first();

            if ($existingAdjustment !== null) {
                throw ValidationException::withMessages([
                    'status' => ['This employee partial pay already has a salary adjustment.'],
                ]);
            }

            $salaryAdjustment = SalaryAdjustment::create([
                'user_id' => $partialPay->employee_id,
                'adjustment_type' => SalaryAdjustment::TYPE_PARTIAL_PAY,
                'reference_type' => EmployeePartialPay::class,
                'reference_id' => $partialPay->id,
                'amount' => $partialPay->gross_amount,
                'reason' => 'Employee partial pay: '.$partialPay->kind,
                'adjustment_date' => $partialPay->service_end_date->toDateString(),
                'payroll_month' => $partialPay->payroll_month,
                'payroll_year' => $partialPay->payroll_year,
                'status' => SalaryAdjustment::STATUS_APPROVED,
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
                'notes' => $reviewNote,
            ]);

            $partialPay->update([
                'status' => EmployeePartialPay::STATUS_APPROVED,
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
                'review_note' => $reviewNote,
                'salary_adjustment_id' => $salaryAdjustment->id,
            ]);

            // Termination must settle outstanding advances immediately.
            if ($partialPay->kind === EmployeePartialPay::KIND_TERMINATION) {
                $this->recoverOutstandingAdvances($partialPay, $reviewer);
            }

            return $partialPay->fresh('salaryAdjustment');
        });
    }

    public function cancel(
        EmployeePartialPay $partialPay,
        ?string $reviewNote,
    ): EmployeePartialPay {
        return DB::transaction(function () use ($partialPay, $reviewNote): EmployeePartialPay {
            $partialPay = EmployeePartialPay::query()->lockForUpdate()->findOrFail($partialPay->id);

            if ($partialPay->status !== EmployeePartialPay::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft employee partial pays can be cancelled.'],
                ]);
            }

            $partialPay->update([
                'status' => EmployeePartialPay::STATUS_CANCELLED,
                'review_note' => $reviewNote,
            ]);

            return $partialPay->fresh('salaryAdjustment');
        });
    }

    private function calculate(User $employee, array $attributes, bool $lockContract = false): array
    {
        $contract = $this->contractForEmployee($employee, (int) $attributes['contractId'], $lockContract);
        $payrollYear = (int) $attributes['payrollYear'];
        $payrollMonth = (int) $attributes['payrollMonth'];
        $kind = $attributes['kind'];
        $baseSalaryCents = $this->positiveBaseSalaryCents($contract);
        [$policy, $policySnapshot] = $this->policySnapshot();
        $workDays = $this->workDays($contract);
        $monthStart = Carbon::create($payrollYear, $payrollMonth, 1)->startOfMonth();

        if ($kind === EmployeePartialPay::KIND_FIRST_MONTH) {
            if ($contract->start_date->day !== 15) {
                throw ValidationException::withMessages([
                    'contractId' => ['Automatic first-month proration is only configured for day-15 hires.'],
                    'kind' => ['Automatic first-month proration is only configured for day-15 hires.'],
                ]);
            }

            if (
                $contract->start_date->year !== $payrollYear
                || $contract->start_date->month !== $payrollMonth
            ) {
                throw ValidationException::withMessages([
                    'payrollMonth' => ['The payroll period must be the contract start month for a first-month partial pay.'],
                ]);
            }

            $serviceStartDate = $contract->start_date->copy();
            $serviceEndDate = $monthStart->copy()->endOfMonth();
            $payableDays = $policySnapshot['midMonthHirePaidDays'];
        } else {
            if (
                $contract->status !== EmploymentContract::STATUS_TERMINATED
                || $contract->terminated_on === null
            ) {
                throw ValidationException::withMessages([
                    'kind' => ['Termination partial pay requires a terminated contract with a terminatedOn date.'],
                ]);
            }

            if (
                $contract->terminated_on->year !== $payrollYear
                || $contract->terminated_on->month !== $payrollMonth
            ) {
                throw ValidationException::withMessages([
                    'payrollMonth' => ['The payroll period must be the contract termination month for a termination partial pay.'],
                ]);
            }

            $serviceStartDate = $monthStart->copy();
            $serviceEndDate = $this->lastScheduledWorkingDay(
                $contract->terminated_on,
                $serviceStartDate,
                $workDays,
            );
            $payableDays = $serviceEndDate->day;
        }

        $dailyRate = $baseSalaryCents / $policySnapshot['dailyRateDivisor'] / 100;
        $grossAmountCents = (int) round(
            $baseSalaryCents * $payableDays / $policySnapshot['dailyRateDivisor'],
            0,
            PHP_ROUND_HALF_UP,
        );

        return [
            'employee_id' => $employee->id,
            'employment_contract_id' => $contract->id,
            'kind' => $kind,
            'payroll_year' => $payrollYear,
            'payroll_month' => $payrollMonth,
            'service_start_date' => $serviceStartDate->toDateString(),
            'service_end_date' => $serviceEndDate->toDateString(),
            'payable_days' => $payableDays,
            'base_salary_snapshot' => Money::fromCents($baseSalaryCents),
            'daily_rate_snapshot' => number_format($dailyRate, 6, '.', ''),
            'gross_amount' => Money::fromCents($grossAmountCents),
            'policy_version_id' => $policy?->id,
            'policy_snapshot' => $policySnapshot,
            'contract_work_days' => $workDays,
            'status' => EmployeePartialPay::STATUS_DRAFT,
        ];
    }

    private function contractForEmployee(User $employee, int $contractId, bool $lock): EmploymentContract
    {
        $query = EmploymentContract::query()
            ->whereKey($contractId)
            ->where('employee_id', $employee->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $contract = $query->first();

        if ($contract === null) {
            throw ValidationException::withMessages([
                'contractId' => ['The selected employment contract does not belong to this employee.'],
            ]);
        }

        return $contract;
    }

    private function policySnapshot(): array
    {
        $policy = HrPolicy::query()
            ->effectiveOn(today()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
        $settings = $policy?->settings ?? [];

        return [$policy, [
            'dailyRateDivisor' => $this->positiveIntegerSetting(
                $settings['dailyRateDivisor'] ?? null,
                self::DEFAULT_DAILY_RATE_DIVISOR,
            ),
            'midMonthHirePaidDays' => $this->paidDaysSetting(
                $settings['midMonthHirePaidDays'] ?? null,
                self::DEFAULT_MID_MONTH_HIRE_PAID_DAYS,
            ),
        ]];
    }

    private function workDays(EmploymentContract $contract): array
    {
        $allowedDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $workDays = array_values(array_unique(array_filter(
            $contract->work_days ?? [],
            fn (mixed $day): bool => is_string($day) && in_array($day, $allowedDays, true),
        )));

        if ($workDays === []) {
            throw ValidationException::withMessages([
                'contractId' => ['The selected employment contract has no valid working days.'],
            ]);
        }

        return $workDays;
    }

    private function lastScheduledWorkingDay(
        Carbon $terminatedOn,
        Carbon $monthStart,
        array $workDays,
    ): Carbon {
        $candidate = $terminatedOn->copy()->startOfDay();

        while ($candidate->greaterThanOrEqualTo($monthStart)) {
            if (in_array(strtolower($candidate->format('l')), $workDays, true)) {
                return $candidate;
            }

            $candidate->subDay();
        }

        throw ValidationException::withMessages([
            'kind' => ['The termination month has no scheduled working day on or before terminatedOn.'],
        ]);
    }

    private function positiveBaseSalaryCents(EmploymentContract $contract): int
    {
        $salaryCents = Money::toCents($contract->base_salary);

        if ($salaryCents <= 0) {
            throw ValidationException::withMessages([
                'contractId' => ['The selected employment contract must have a positive baseSalary.'],
            ]);
        }

        return $salaryCents;
    }

    private function positiveIntegerSetting(mixed $value, int $default): int
    {
        return is_int($value) && $value > 0 ? $value : $default;
    }

    private function paidDaysSetting(mixed $value, int $default): int
    {
        return is_int($value) && $value >= 0 && $value <= 31 ? $value : $default;
    }

    /**
     * On termination approval, accelerate all outstanding advance
     * settlements into immediate recovery adjustments in the termination
     * payroll month so no open balance survives the final payout.
     */
    private function recoverOutstandingAdvances(EmployeePartialPay $partialPay, User $reviewer): void
    {
        $advanceIds = EmployeeAdvance::query()
            ->where('employee_id', $partialPay->employee_id)
            ->whereIn('status', [EmployeeAdvance::STATUS_APPROVED, EmployeeAdvance::STATUS_DISBURSED])
            ->lockForUpdate()
            ->pluck('id');

        if ($advanceIds->isEmpty()) {
            return;
        }

        $settlements = EmployeeAdvanceSettlement::query()
            ->whereIn('employee_advance_id', $advanceIds)
            ->where('status', EmployeeAdvanceSettlement::STATUS_SCHEDULED)
            ->lockForUpdate()
            ->orderBy('employee_advance_id')
            ->orderBy('sequence')
            ->get();

        foreach ($settlements as $settlement) {
            $adjustment = SalaryAdjustment::create([
                'user_id' => $partialPay->employee_id,
                'adjustment_type' => SalaryAdjustment::TYPE_ADVANCE_RECOVERY,
                'reference_type' => EmployeeAdvanceSettlement::class,
                'reference_id' => $settlement->id,
                'amount' => $settlement->scheduled_amount,
                'reason' => 'Termination recovery for advance #'.$settlement->employee_advance_id,
                'adjustment_date' => $partialPay->service_end_date->toDateString(),
                'payroll_month' => $partialPay->payroll_month,
                'payroll_year' => $partialPay->payroll_year,
                'status' => SalaryAdjustment::STATUS_APPROVED,
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
                'notes' => 'Accelerated on termination partial pay #'.$partialPay->id,
            ]);

            $settlement->update([
                'status' => EmployeeAdvanceSettlement::STATUS_SETTLED,
                'settled_by' => $reviewer->id,
                'settled_at' => now(),
                'settlement_reference' => 'termination-partial-pay-'.$partialPay->id,
                'salary_adjustment_id' => $adjustment->id,
            ]);
        }

        $remainingByAdvance = EmployeeAdvanceSettlement::query()
            ->whereIn('employee_advance_id', $advanceIds)
            ->where('status', EmployeeAdvanceSettlement::STATUS_SCHEDULED)
            ->lockForUpdate()
            ->count();

        if ($remainingByAdvance === 0) {
            EmployeeAdvance::query()
                ->whereIn('id', $advanceIds)
                ->where('status', EmployeeAdvance::STATUS_DISBURSED)
                ->update(['status' => EmployeeAdvance::STATUS_SETTLED]);
        }
    }
}
