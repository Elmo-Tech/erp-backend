<?php

namespace App\Services\HR;

use App\Models\Attendance;
use App\Models\EmployeeLateDeduction;
use App\Models\EmployeeShortPermission;
use App\Models\HrPolicy;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LateDeductionEvaluator
{
    /**
     * Create only missing ledger records. Existing results retain their original policy snapshot.
     *
     * @return Collection<int, EmployeeLateDeduction>
     */
    public function evaluate(User $employee, int $year, int $month, int $evaluatorId): Collection
    {
        [$policy, $dailyRateDivisor, $tiers] = $this->activePolicySettings();
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return DB::transaction(function () use (
            $employee,
            $year,
            $month,
            $evaluatorId,
            $policy,
            $dailyRateDivisor,
            $tiers,
            $monthStart,
            $monthEnd,
        ): Collection {
            $employee = User::query()->lockForUpdate()->findOrFail($employee->id);
            $attendances = Attendance::query()
                ->where('user_id', $employee->id)
                ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->where('late_minutes', '>=', 15)
                ->orderBy('date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $recordsByAttendance = EmployeeLateDeduction::query()
                ->whereIn('attendance_id', $attendances->pluck('id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('attendance_id');
            $permissionsByDate = $this->validPaidPermissions($employee->id, $monthStart, $monthEnd)
                ->groupBy(fn (EmployeeShortPermission $permission): string => $permission->permission_date->toDateString());
            $occurrence = 0;

            foreach ($attendances as $attendance) {
                /** @var EmployeeLateDeduction|null $existing */
                $existing = $recordsByAttendance->get($attendance->id);

                if ($existing !== null) {
                    if ($existing->status !== EmployeeLateDeduction::STATUS_WAIVED) {
                        $occurrence++;
                    }

                    continue;
                }

                $overlapMinutes = $this->paidPermissionOverlapMinutes(
                    $attendance,
                    $permissionsByDate->get($attendance->date->toDateString(), new Collection)
                );
                $eligibleLateMinutes = max(0, $attendance->late_minutes - $overlapMinutes);
                $baseAttributes = [
                    'employee_id' => $employee->id,
                    'attendance_id' => $attendance->id,
                    'policy_version_id' => $policy->id,
                    'policy_snapshot' => $policy->settings,
                    'attendance_date' => $attendance->date->toDateString(),
                    'original_late_minutes' => $attendance->late_minutes,
                    'eligible_late_minutes' => $eligibleLateMinutes,
                    'daily_rate' => round(((float) $employee->salary) / $dailyRateDivisor, 2),
                    'evaluated_by' => $evaluatorId,
                    'evaluated_at' => now(),
                ];

                if ($eligibleLateMinutes < 15) {
                    EmployeeLateDeduction::create(array_merge($baseAttributes, [
                        'percentage' => 0,
                        'calculated_amount' => 0,
                        'status' => EmployeeLateDeduction::STATUS_WAIVED,
                        'waiver_reason' => "Paid, pre-recorded short permission coverage reduced lateness from {$attendance->late_minutes} to {$eligibleLateMinutes} minutes.",
                    ]));

                    continue;
                }

                $tier = $this->tierForMinutes($tiers, $eligibleLateMinutes);
                $occurrence++;
                $percentage = $tier['percentages'][min($occurrence - 1, 3)];
                $dailyRate = ((float) $employee->salary) / $dailyRateDivisor;
                $calculatedAmount = round($dailyRate * ((float) $percentage) / 100, 2);
                $record = EmployeeLateDeduction::create(array_merge($baseAttributes, [
                    'occurrence_number' => $occurrence,
                    'tier_minimum_minutes' => $tier['minimumMinutes'],
                    'tier_maximum_minutes' => $tier['maximumMinutes'],
                    'percentage' => $percentage,
                    'calculated_amount' => $calculatedAmount,
                    'status' => EmployeeLateDeduction::STATUS_CALCULATED,
                ]));
                $adjustment = SalaryAdjustment::firstOrCreate([
                    'reference_type' => EmployeeLateDeduction::class,
                    'reference_id' => $record->id,
                ], [
                    'user_id' => $employee->id,
                    'adjustment_type' => SalaryAdjustment::TYPE_DEDUCTION,
                    'amount' => $calculatedAmount,
                    'reason' => 'Policy-driven late deduction for '.$attendance->date->toDateString(),
                    'adjustment_date' => $attendance->date->toDateString(),
                    'payroll_month' => $month,
                    'payroll_year' => $year,
                    'status' => SalaryAdjustment::STATUS_APPROVED,
                    'approved_by' => $evaluatorId,
                    'approved_at' => now(),
                    'notes' => 'Automatically approved from policy-driven late deduction evaluation.',
                ]);

                $record->update(['salary_adjustment_id' => $adjustment->id]);
            }

            return $this->recordsForPeriod($employee->id, $monthStart, $monthEnd);
        });
    }

    /**
     * @return Collection<int, EmployeeLateDeduction>
     */
    public function recordsForPeriod(int $employeeId, Carbon $monthStart, Carbon $monthEnd): Collection
    {
        return EmployeeLateDeduction::query()
            ->with('salaryAdjustment')
            ->where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('attendance_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: HrPolicy, 1: int, 2: list<array{minimumMinutes: int, maximumMinutes: int|null, percentages: list<float|int>}>}
     */
    private function activePolicySettings(): array
    {
        $policy = HrPolicy::query()
            ->effectiveOn(today()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($policy === null || ! is_array($policy->settings)) {
            $this->invalidPolicy('An active HR policy with late deduction settings is required.');
        }

        $dailyRateDivisor = $policy->settings['dailyRateDivisor'] ?? null;
        $tiers = $policy->settings['lateDeductionTiers'] ?? null;

        if (! is_int($dailyRateDivisor) || $dailyRateDivisor < 1) {
            $this->invalidPolicy('The active HR policy has an invalid dailyRateDivisor.');
        }

        if (! is_array($tiers) || $tiers === []) {
            $this->invalidPolicy('The active HR policy has no lateDeductionTiers.');
        }

        $validatedTiers = [];
        $previousMaximum = null;
        $previousTierWasUnbounded = false;

        foreach ($tiers as $tier) {
            if (! is_array($tier)
                || ! array_key_exists('minimumMinutes', $tier)
                || ! array_key_exists('maximumMinutes', $tier)
                || ! array_key_exists('percentages', $tier)
                || ! is_int($tier['minimumMinutes'])
                || $tier['minimumMinutes'] < 0
                || ($tier['maximumMinutes'] !== null && (! is_int($tier['maximumMinutes']) || $tier['maximumMinutes'] < $tier['minimumMinutes']))
                || ! is_array($tier['percentages'])
                || count($tier['percentages']) !== 4
                || $previousTierWasUnbounded
                || ($previousMaximum !== null && $tier['minimumMinutes'] <= $previousMaximum)
            ) {
                $this->invalidPolicy('The active HR policy has malformed lateDeductionTiers.');
            }

            foreach ($tier['percentages'] as $percentage) {
                if ((! is_int($percentage) && ! is_float($percentage)) || $percentage < 0 || $percentage > 100) {
                    $this->invalidPolicy('The active HR policy has malformed lateDeductionTiers percentages.');
                }
            }

            $validatedTiers[] = [
                'minimumMinutes' => $tier['minimumMinutes'],
                'maximumMinutes' => $tier['maximumMinutes'],
                'percentages' => array_values($tier['percentages']),
            ];
            $previousTierWasUnbounded = $tier['maximumMinutes'] === null;
            $previousMaximum = $tier['maximumMinutes'];
        }

        return [$policy, $dailyRateDivisor, $validatedTiers];
    }

    /**
     * @param  list<array{minimumMinutes: int, maximumMinutes: int|null, percentages: list<float|int>}>  $tiers
     * @return array{minimumMinutes: int, maximumMinutes: int|null, percentages: list<float|int>}
     */
    private function tierForMinutes(array $tiers, int $minutes): array
    {
        foreach ($tiers as $tier) {
            if ($minutes >= $tier['minimumMinutes']
                && ($tier['maximumMinutes'] === null || $minutes <= $tier['maximumMinutes'])) {
                return $tier;
            }
        }

        $this->invalidPolicy("The active HR policy has no lateDeductionTier for {$minutes} eligible late minutes.");
    }

    /**
     * @return Collection<int, EmployeeShortPermission>
     */
    private function validPaidPermissions(int $employeeId, Carbon $monthStart, Carbon $monthEnd): Collection
    {
        return EmployeeShortPermission::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('permission_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('status', EmployeeShortPermission::STATUS_APPROVED)
            ->where('is_paid', true)
            ->whereNotNull('reviewed_at')
            ->orderBy('permission_date')
            ->orderBy('start_time')
            ->get()
            ->filter(function (EmployeeShortPermission $permission): bool {
                $permissionStart = $permission->permission_date->copy()->startOfDay();

                return $permission->created_at !== null
                    && $permission->reviewed_at !== null
                    && $permission->created_at->lte($permissionStart)
                    && $permission->reviewed_at->lte($permissionStart);
            })
            ->values();
    }

    /**
     * @param  Collection<int, EmployeeShortPermission>  $permissions
     */
    private function paidPermissionOverlapMinutes(Attendance $attendance, Collection $permissions): int
    {
        if ($attendance->expected_clock_in === null || $attendance->clock_in === null) {
            return 0;
        }

        $date = $attendance->date->toDateString();
        $lateStart = Carbon::parse($date.' '.$attendance->expected_clock_in);
        $lateEnd = $attendance->clock_in->copy();

        if ($lateEnd->lte($lateStart)) {
            return 0;
        }

        $intervals = [];

        foreach ($permissions as $permission) {
            $permissionStart = Carbon::parse($date.' '.$permission->start_time);
            $permissionEnd = Carbon::parse($date.' '.$permission->end_time);
            $start = $permissionStart->greaterThan($lateStart) ? $permissionStart : $lateStart;
            $end = $permissionEnd->lessThan($lateEnd) ? $permissionEnd : $lateEnd;

            if ($end->gt($start)) {
                $intervals[] = [$start->getTimestamp(), $end->getTimestamp()];
            }
        }

        usort($intervals, fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $coveredSeconds = 0;
        $mergedStart = null;
        $mergedEnd = null;

        foreach ($intervals as [$start, $end]) {
            if ($mergedStart === null) {
                $mergedStart = $start;
                $mergedEnd = $end;

                continue;
            }

            if ($start <= $mergedEnd) {
                $mergedEnd = max($mergedEnd, $end);

                continue;
            }

            $coveredSeconds += $mergedEnd - $mergedStart;
            $mergedStart = $start;
            $mergedEnd = $end;
        }

        if ($mergedStart !== null) {
            $coveredSeconds += $mergedEnd - $mergedStart;
        }

        return (int) floor($coveredSeconds / 60);
    }

    private function invalidPolicy(string $message): never
    {
        throw ValidationException::withMessages(['policy' => [$message]]);
    }
}
