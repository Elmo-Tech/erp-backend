<?php

namespace Tests\Unit\Services;

use App\Services\HR\SalaryRateCalculator;
use PHPUnit\Framework\TestCase;

class SalaryRateCalculatorTest extends TestCase
{
    public function test_it_uses_five_working_days_and_average_weeks_per_month(): void
    {
        $calculator = new SalaryRateCalculator;

        $this->assertSame(173.33, $calculator->monthlyWorkHours(8));
        $this->assertSame(138.46, $calculator->hourlyRate(24000, 8));
    }

    public function test_it_returns_null_when_salary_is_not_set(): void
    {
        $calculator = new SalaryRateCalculator;

        $this->assertNull($calculator->hourlyRate(null, 8));
    }
}
