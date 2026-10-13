<?php

namespace App\Services\HR;

use App\Models\EmploymentContract;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

class WorkCommitmentService
{
    public function summary(User $employee, Carbon $from, Carbon $to): array
    {
        $contracts = $employee->employmentContracts()
            ->where('status', '!=', EmploymentContract::STATUS_DRAFT)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->orderByDesc('start_date')->orderByDesc('id')->get();
        $requiredMinutes = 0.0;
        $hasPartTimeTarget = false;

        // Pick the latest agreement for each service day, including retained
        // renewal history. Partial months receive a proportional monthly target.
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $contract = $contracts->first(fn (EmploymentContract $item): bool => $item->start_date->lte($day) && $item->end_date->gte($day)
                && ($item->terminated_on === null || $item->terminated_on->gte($day)));
            if ($contract === null) {
                continue;
            }
            if ($contract->employment_type === 'part_time' && $contract->required_monthly_hours > 0) {
                $requiredMinutes += (float) $contract->required_monthly_hours * 60 / $day->daysInMonth;
                $hasPartTimeTarget = true;
            } elseif (in_array(strtolower($day->format('l')), $contract->work_days ?? [], true)) {
                $requiredMinutes += (float) $contract->work_hours_per_day * 60;
            }
        }

        $workedMinutes = (int) TimeEntry::where('user_id', $employee->id)
            ->whereDate('start_time', '>=', $from->toDateString())
            ->whereDate('start_time', '<=', $to->toDateString())
            ->whereNotNull('end_time')->sum('duration_minutes');
        $target = (int) round($requiredMinutes);
        $current = $contracts->first();

        return [
            'employmentType' => $current?->employment_type,
            'requiredMonthlyHours' => $current?->required_monthly_hours === null ? null : (float) $current->required_monthly_hours,
            'requiredMinutes' => $target,
            'workedMinutes' => $workedMinutes,
            'remainingMinutes' => max(0, $target - $workedMinutes),
            'completionPercentage' => $target > 0 ? round($workedMinutes / $target * 100, 2) : null,
            'hasPartTimeTarget' => $hasPartTimeTarget,
        ];
    }
}
