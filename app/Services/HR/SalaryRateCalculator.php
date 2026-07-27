<?php

namespace App\Services\HR;

class SalaryRateCalculator
{
    public const WORKING_DAYS_PER_WEEK = 5;

    public function monthlyWorkHours(float $workHoursPerDay): float
    {
        if ($workHoursPerDay <= 0) {
            return 0.0;
        }

        return round(
            $workHoursPerDay * self::WORKING_DAYS_PER_WEEK * 52 / 12,
            2
        );
    }

    public function hourlyRate(?float $monthlySalary, float $workHoursPerDay): ?float
    {
        if ($monthlySalary === null) {
            return null;
        }

        $monthlyHours = $this->monthlyWorkHours($workHoursPerDay);

        if ($monthlyHours <= 0) {
            return null;
        }

        return round($monthlySalary / $monthlyHours, 2);
    }
}
