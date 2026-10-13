<?php

namespace App\Services\HR;

use App\Models\EmploymentContract;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

class EmployeeSalaryPeriodService
{
    public function calculate(User $employee, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $contracts = $employee->employmentContracts()->where('status', '!=', EmploymentContract::STATUS_DRAFT)
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->orderByDesc('start_date')->orderByDesc('id')->get();
        $grossCents = 0.0;
        $serviceDays = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $contract = $contracts->first(fn (EmploymentContract $item): bool => $item->start_date->lte($day) && $item->end_date->gte($day)
                && ($item->terminated_on === null || $item->terminated_on->gte($day)));
            if ($contract === null) {
                continue;
            }
            $grossCents += Money::toCents($contract->base_salary) / $start->daysInMonth;
            $serviceDays++;
        }
        if ($contracts->isEmpty() && ! $employee->employmentContracts()->where('status', '!=', EmploymentContract::STATUS_DRAFT)->exists()) {
            $grossCents = Money::toCents($employee->salary ?? 0);
            $serviceDays = $start->daysInMonth;
        }
        $adjustments = SalaryAdjustment::where('user_id', $employee->id)->forPayrollPeriod($start->year, $start->month)
            ->whereIn('status', [SalaryAdjustment::STATUS_APPROVED, SalaryAdjustment::STATUS_PROCESSED])->get();
        // An approved historical partial-pay record replaces salary; it is not
        // added to the contract salary a second time.
        $partialPay = $adjustments->where('adjustment_type', SalaryAdjustment::TYPE_PARTIAL_PAY)->first();
        if ($partialPay !== null) {
            $grossCents = Money::toCents($partialPay->amount);
        }
        $additionCents = $adjustments->filter(fn ($item) => $item->isPositive() && $item->adjustment_type !== SalaryAdjustment::TYPE_PARTIAL_PAY)
            ->sum(fn ($item) => Money::toCents($item->amount));
        $deductionCents = $adjustments->filter(fn ($item) => $item->isNegative())->sum(fn ($item) => Money::toCents($item->amount));

        return [
            'month' => $start->format('Y-m'),
            'serviceDays' => $serviceDays,
            'calendarDays' => $start->daysInMonth,
            'grossSalary' => (float) Money::fromCents((int) round($grossCents)),
            'additions' => (float) Money::fromCents($additionCents),
            'totalDeductions' => (float) Money::fromCents($deductionCents),
            'netSalary' => (float) Money::fromCents((int) round($grossCents) + $additionCents - $deductionCents),
            'prorationMethod' => 'inclusive_calendar_days',
        ];
    }
}
