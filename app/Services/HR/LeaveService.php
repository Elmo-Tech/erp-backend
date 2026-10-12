<?php

namespace App\Services\HR;

use App\Models\EmploymentContract;
use App\Models\HrPolicy;
use Illuminate\Support\Carbon;

class LeaveService
{
    private const FALLBACK_WORKING_DAYS = [
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
    ];

    private const WEEKDAY_NUMBERS = [
        'sunday' => Carbon::SUNDAY,
        'monday' => Carbon::MONDAY,
        'tuesday' => Carbon::TUESDAY,
        'wednesday' => Carbon::WEDNESDAY,
        'thursday' => Carbon::THURSDAY,
        'friday' => Carbon::FRIDAY,
        'saturday' => Carbon::SATURDAY,
    ];

    /**
     * @return array{0: int, 1: HrPolicy|null}
     */
    public function calculateWorkingDays(string $startDate, string $endDate, ?int $employeeId = null): array
    {
        $policy = $this->currentPolicy();
        $contract = $employeeId === null ? null : EmploymentContract::where('employee_id', $employeeId)
            ->where('status', EmploymentContract::STATUS_ACTIVE)->first();
        $workingDayNumbers = $this->workingDayNumbers($policy, $contract?->work_days);
        $workingDays = 0;

        for ($date = Carbon::parse($startDate); $date->lte($endDate); $date->addDay()) {
            if (in_array($date->dayOfWeek, $workingDayNumbers, true)) {
                $workingDays++;
            }
        }

        return [$workingDays, $policy];
    }

    public function currentPolicy(): ?HrPolicy
    {
        return HrPolicy::query()
            ->effectiveOn(today()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{0: int, 1: HrPolicy|null}
     */
    public function monthlyPaidPermissionMinutes(): array
    {
        $policy = $this->currentPolicy();

        if ($policy === null) {
            return [6 * 60, null];
        }

        $hours = (float) ($policy->settings['monthlyPaidPermissionHours'] ?? 0);

        return [(int) round($hours * 60), $policy];
    }

    /**
     * @return list<int>
     */
    private function workingDayNumbers(?HrPolicy $policy, ?array $contractDays = null): array
    {
        $settings = $policy?->settings;
        $configuredDays = $contractDays ?? (is_array($settings)
            ? ($settings['workingDays'] ?? self::FALLBACK_WORKING_DAYS)
            : self::FALLBACK_WORKING_DAYS);
        $dayNumbers = [];

        if (is_array($configuredDays)) {
            foreach ($configuredDays as $day) {
                $normalizedDay = is_string($day) ? strtolower($day) : null;

                if ($normalizedDay !== null && array_key_exists($normalizedDay, self::WEEKDAY_NUMBERS)) {
                    $dayNumbers[] = self::WEEKDAY_NUMBERS[$normalizedDay];
                }
            }
        }

        if ($dayNumbers === []) {
            return array_map(
                fn (string $day): int => self::WEEKDAY_NUMBERS[$day],
                self::FALLBACK_WORKING_DAYS
            );
        }

        return array_values(array_unique($dayNumbers));
    }
}
