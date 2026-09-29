<?php

namespace App\Services\HR;

use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceSettlement;
use App\Models\HrPolicy;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeAdvanceService
{
    private const DEFAULT_ADVANCE_LIMIT_SALARY_PERCENTAGE = 100;

    private const DEFAULT_ADVANCE_SETTLEMENT = 'next_payroll';

    private const DEFAULT_ALLOW_ADVANCE_INSTALLMENTS = true;

    private const DEFAULT_PAYROLL_PAYMENT_DAY = 1;

    public function create(User $employee, array $attributes): EmployeeAdvance
    {
        return DB::transaction(function () use ($employee, $attributes): EmployeeAdvance {
            $employee = User::query()->lockForUpdate()->findOrFail($employee->id);
            $snapshot = $this->policySnapshot($this->currentPolicy());
            $salaryCents = $this->positiveSalaryCents($employee, 'requestedAmount');
            $requestedCents = $this->decimalToCents($attributes['requestedAmount']);
            $limitCents = $this->limitCents($salaryCents, $snapshot['advanceLimitSalaryPercentage']);

            if ($requestedCents > $limitCents) {
                throw ValidationException::withMessages([
                    'requestedAmount' => ['The requested amount exceeds the employee advance limit.'],
                ]);
            }

            $this->assertInstallmentCountAllowed((int) $attributes['installmentCount'], $snapshot);

            return EmployeeAdvance::create([
                'employee_id' => $employee->id,
                'requested_amount' => $this->centsToDecimal($requestedCents),
                'status' => EmployeeAdvance::STATUS_PENDING,
                'reason' => $attributes['reason'],
                'installment_count' => (int) $attributes['installmentCount'],
            ]);
        });
    }

    public function approve(
        EmployeeAdvance $advance,
        User $reviewer,
        mixed $approvedAmount,
        ?string $reviewNote,
    ): EmployeeAdvance {
        return DB::transaction(function () use ($advance, $reviewer, $approvedAmount, $reviewNote): EmployeeAdvance {
            $employee = User::query()->lockForUpdate()->findOrFail($advance->employee_id);
            $advance = EmployeeAdvance::query()->lockForUpdate()->findOrFail($advance->id);
            $this->assertPending($advance, 'reviewed');

            $policy = $this->currentPolicy();
            $snapshot = $this->policySnapshot($policy);
            $this->assertInstallmentCountAllowed($advance->installment_count, $snapshot);
            $salaryCents = $this->positiveSalaryCents($employee, 'approvedAmount');
            $limitCents = $this->limitCents($salaryCents, $snapshot['advanceLimitSalaryPercentage']);
            $approvedCents = $approvedAmount === null
                ? $this->decimalToCents($advance->requested_amount)
                : $this->decimalToCents($approvedAmount);

            if ($approvedCents <= 0 || $approvedCents > $this->decimalToCents($advance->requested_amount)) {
                throw ValidationException::withMessages([
                    'approvedAmount' => ['The approved amount must be positive and cannot exceed the requested amount.'],
                ]);
            }

            $reservedCents = $this->activeExposureCents($employee->id);

            if ($reservedCents + $approvedCents > $limitCents) {
                throw ValidationException::withMessages([
                    'approvedAmount' => ['The approved amount exceeds the employee advance limit after active advances.'],
                ]);
            }

            $advance->update([
                'approved_amount' => $this->centsToDecimal($approvedCents),
                'status' => EmployeeAdvance::STATUS_APPROVED,
                'policy_version_id' => $policy?->id,
                'policy_snapshot' => $snapshot,
                'salary_at_approval' => $this->centsToDecimal($salaryCents),
                'advance_limit_amount' => $this->centsToDecimal($limitCents),
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
                'review_note' => $reviewNote,
            ]);

            return $advance->fresh('settlements');
        });
    }

    public function reject(EmployeeAdvance $advance, User $reviewer, ?string $reviewNote): EmployeeAdvance
    {
        return DB::transaction(function () use ($advance, $reviewer, $reviewNote): EmployeeAdvance {
            $advance = EmployeeAdvance::query()->lockForUpdate()->findOrFail($advance->id);
            $this->assertPending($advance, 'reviewed');

            $advance->update([
                'status' => EmployeeAdvance::STATUS_REJECTED,
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
                'review_note' => $reviewNote,
            ]);

            return $advance->fresh('settlements');
        });
    }

    public function cancel(EmployeeAdvance $advance, User $actor, ?string $reviewNote): EmployeeAdvance
    {
        return DB::transaction(function () use ($advance, $actor, $reviewNote): EmployeeAdvance {
            $advance = EmployeeAdvance::query()->lockForUpdate()->findOrFail($advance->id);
            $allowedStatuses = $actor->id === $advance->employee_id
                ? [EmployeeAdvance::STATUS_PENDING]
                : [EmployeeAdvance::STATUS_PENDING, EmployeeAdvance::STATUS_APPROVED];

            if (! in_array($advance->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ['This employee advance cannot be cancelled in its current status.'],
                ]);
            }

            $advance->update([
                'status' => EmployeeAdvance::STATUS_CANCELLED,
                'review_note' => $reviewNote,
            ]);

            return $advance->fresh('settlements');
        });
    }

    public function disburse(
        EmployeeAdvance $advance,
        User $disburser,
        ?string $disbursementReference,
    ): EmployeeAdvance {
        return DB::transaction(function () use ($advance, $disburser, $disbursementReference): EmployeeAdvance {
            $employee = User::query()->lockForUpdate()->findOrFail($advance->employee_id);
            $advance = EmployeeAdvance::query()->lockForUpdate()->findOrFail($advance->id);

            if ($advance->status !== EmployeeAdvance::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => ['Only approved employee advances can be disbursed.'],
                ]);
            }

            $reservedCents = $this->activeExposureCents($employee->id);
            $limitCents = $this->decimalToCents($advance->advance_limit_amount ?? 0);

            if ($limitCents <= 0 || $reservedCents > $limitCents) {
                throw ValidationException::withMessages([
                    'approvedAmount' => ['The approved amount exceeds the employee advance limit after active advances.'],
                ]);
            }

            if (EmployeeAdvanceSettlement::query()
                ->where('employee_advance_id', $advance->id)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => ['The employee advance already has a recovery schedule.'],
                ]);
            }

            $approvedCents = $this->decimalToCents($advance->approved_amount);
            $snapshot = $this->policySnapshotFromAdvance($advance);
            $firstDueOn = $this->firstPayrollDate($snapshot['payrollPaymentDay']);
            $baseInstallmentCents = intdiv($approvedCents, $advance->installment_count);
            $lastInstallmentCents = $approvedCents
                - ($baseInstallmentCents * ($advance->installment_count - 1));

            $advance->update([
                'disbursed_amount' => $this->centsToDecimal($approvedCents),
                'status' => EmployeeAdvance::STATUS_DISBURSED,
                'disbursed_by' => $disburser->id,
                'disbursed_at' => now(),
                'disbursement_reference' => $disbursementReference,
            ]);

            for ($sequence = 1; $sequence <= $advance->installment_count; $sequence++) {
                $dueMonth = $firstDueOn->copy()->startOfMonth()->addMonths($sequence - 1);
                $dueOn = $this->payrollDateForMonth($dueMonth, $snapshot['payrollPaymentDay']);

                EmployeeAdvanceSettlement::create([
                    'employee_advance_id' => $advance->id,
                    'sequence' => $sequence,
                    'due_on' => $dueOn->toDateString(),
                    'scheduled_amount' => $this->centsToDecimal(
                        $sequence === $advance->installment_count ? $lastInstallmentCents : $baseInstallmentCents
                    ),
                    'status' => EmployeeAdvanceSettlement::STATUS_SCHEDULED,
                ]);
            }

            return $advance->fresh('settlements');
        });
    }

    public function settle(
        EmployeeAdvanceSettlement $settlement,
        User $settler,
        ?string $settlementReference,
    ): EmployeeAdvanceSettlement {
        return DB::transaction(function () use ($settlement, $settler, $settlementReference): EmployeeAdvanceSettlement {
            $advance = EmployeeAdvance::query()->findOrFail($settlement->employee_advance_id);
            User::query()->whereKey($advance->employee_id)->lockForUpdate()->firstOrFail();
            $advance = EmployeeAdvance::query()
                ->lockForUpdate()
                ->findOrFail($settlement->employee_advance_id);
            $settlement = EmployeeAdvanceSettlement::query()->lockForUpdate()->findOrFail($settlement->id);

            if ($advance->status !== EmployeeAdvance::STATUS_DISBURSED) {
                throw ValidationException::withMessages([
                    'status' => ['Only disbursed employee advances can have settlements recorded.'],
                ]);
            }

            if ($settlement->status !== EmployeeAdvanceSettlement::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'status' => ['Only scheduled employee advance settlements can be settled.'],
                ]);
            }

            $salaryAdjustment = SalaryAdjustment::create([
                'user_id' => $advance->employee_id,
                'adjustment_type' => SalaryAdjustment::TYPE_ADVANCE_RECOVERY,
                'reference_type' => EmployeeAdvanceSettlement::class,
                'reference_id' => $settlement->id,
                'amount' => $settlement->scheduled_amount,
                'reason' => 'Salary advance recovery: '.$advance->reason,
                'adjustment_date' => $settlement->due_on->toDateString(),
                'payroll_month' => $settlement->due_on->month,
                'payroll_year' => $settlement->due_on->year,
                'status' => SalaryAdjustment::STATUS_APPROVED,
                'approved_by' => $settler->id,
                'approved_at' => now(),
            ]);

            $settlement->update([
                'status' => EmployeeAdvanceSettlement::STATUS_SETTLED,
                'settled_by' => $settler->id,
                'settled_at' => now(),
                'settlement_reference' => $settlementReference,
                'salary_adjustment_id' => $salaryAdjustment->id,
            ]);

            $remainingCents = $this->decimalToCents(
                EmployeeAdvanceSettlement::query()
                    ->where('employee_advance_id', $advance->id)
                    ->where('status', EmployeeAdvanceSettlement::STATUS_SCHEDULED)
                    ->lockForUpdate()
                    ->sum('scheduled_amount')
            );

            if ($remainingCents === 0) {
                $advance->update(['status' => EmployeeAdvance::STATUS_SETTLED]);
            }

            return $settlement->fresh(['advance.settlements', 'salaryAdjustment']);
        });
    }

    private function currentPolicy(): ?HrPolicy
    {
        return HrPolicy::query()
            ->effectiveOn(today())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    private function policySnapshot(?HrPolicy $policy): array
    {
        $settings = $policy?->settings ?? [];

        return [
            'advanceLimitSalaryPercentage' => $this->percentageSetting($settings['advanceLimitSalaryPercentage'] ?? null),
            'advanceSettlement' => $this->settlementSetting($settings['advanceSettlement'] ?? null),
            'allowAdvanceInstallments' => $this->booleanSetting($settings['allowAdvanceInstallments'] ?? null),
            'payrollPaymentDay' => $this->paymentDaySetting($settings['payrollPaymentDay'] ?? null),
        ];
    }

    private function policySnapshotFromAdvance(EmployeeAdvance $advance): array
    {
        $snapshot = is_array($advance->policy_snapshot) ? $advance->policy_snapshot : [];

        return [
            'advanceLimitSalaryPercentage' => $this->percentageSetting($snapshot['advanceLimitSalaryPercentage'] ?? null),
            'advanceSettlement' => $this->settlementSetting($snapshot['advanceSettlement'] ?? null),
            'allowAdvanceInstallments' => $this->booleanSetting($snapshot['allowAdvanceInstallments'] ?? null),
            'payrollPaymentDay' => $this->paymentDaySetting($snapshot['payrollPaymentDay'] ?? null),
        ];
    }

    private function activeExposureCents(int $employeeId): int
    {
        $advances = EmployeeAdvance::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [EmployeeAdvance::STATUS_APPROVED, EmployeeAdvance::STATUS_DISBURSED])
            ->lockForUpdate()
            ->get();
        $settlementsByAdvance = EmployeeAdvanceSettlement::query()
            ->whereIn('employee_advance_id', $advances->pluck('id'))
            ->lockForUpdate()
            ->get()
            ->groupBy('employee_advance_id');
        $exposureCents = 0;

        foreach ($advances as $activeAdvance) {
            if ($activeAdvance->status === EmployeeAdvance::STATUS_APPROVED) {
                $exposureCents += $this->decimalToCents($activeAdvance->approved_amount);

                continue;
            }

            foreach ($settlementsByAdvance->get($activeAdvance->id, []) as $settlement) {
                if ($settlement->status === EmployeeAdvanceSettlement::STATUS_SCHEDULED) {
                    $exposureCents += $this->decimalToCents($settlement->scheduled_amount);
                }
            }
        }

        return $exposureCents;
    }

    private function assertPending(EmployeeAdvance $advance, string $action): void
    {
        if ($advance->status !== EmployeeAdvance::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ["Only pending employee advances can be {$action}."],
            ]);
        }
    }

    private function assertInstallmentCountAllowed(int $installmentCount, array $snapshot): void
    {
        if (! $snapshot['allowAdvanceInstallments'] && $installmentCount !== 1) {
            throw ValidationException::withMessages([
                'installmentCount' => ['Employee advance installments are disabled by the current HR policy.'],
            ]);
        }
    }

    private function positiveSalaryCents(User $employee, string $field): int
    {
        $salaryCents = $this->decimalToCents($employee->salary ?? 0);

        if ($salaryCents <= 0) {
            throw ValidationException::withMessages([
                $field => ['The employee must have a positive current base monthly salary.'],
            ]);
        }

        return $salaryCents;
    }

    private function limitCents(int $salaryCents, int $percentage): int
    {
        return (int) round($salaryCents * $percentage / 100);
    }

    private function percentageSetting(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_ADVANCE_LIMIT_SALARY_PERCENTAGE;
        }

        return min(100, max(0, (int) $value));
    }

    private function settlementSetting(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : self::DEFAULT_ADVANCE_SETTLEMENT;
    }

    private function booleanSetting(mixed $value): bool
    {
        if ($value === null) {
            return self::DEFAULT_ALLOW_ADVANCE_INSTALLMENTS;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? self::DEFAULT_ALLOW_ADVANCE_INSTALLMENTS;
    }

    private function paymentDaySetting(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_PAYROLL_PAYMENT_DAY;
        }

        return min(31, max(1, (int) $value));
    }

    private function firstPayrollDate(int $paymentDay): Carbon
    {
        $month = today()->startOfMonth();
        $payrollDate = $this->payrollDateForMonth($month, $paymentDay);

        return today()->greaterThan($payrollDate)
            ? $this->payrollDateForMonth($month->addMonth(), $paymentDay)
            : $payrollDate;
    }

    private function payrollDateForMonth(Carbon $month, int $paymentDay): Carbon
    {
        $month = $month->copy()->startOfMonth();

        return $month->setDay(min($paymentDay, $month->daysInMonth));
    }

    private function decimalToCents(mixed $amount): int
    {
        $amount = (string) $amount;

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches) === 1) {
            return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
        }

        return (int) round((float) $amount * 100);
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
