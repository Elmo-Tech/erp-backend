<?php

namespace App\Modules\HR\Services;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\PayrollTransaction;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Carbon;

/**
 * Payroll Calculation Engine.
 *
 * All monetary arithmetic lives here — never in a controller.
 * The service is intentionally stateless; every public method receives
 * its inputs and returns a plain array or Eloquent model.
 *
 * Formula:
 *   net_salary = base_salary
 *              + points_bonus_amount
 *              + manual_bonus_total
 *              - lateness_deduction_amount
 *              - manual_deduction_total
 *              - unpaid_leave_deduction
 */
class PayrollCalculatorService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Calculate payroll components for a single employee for a given month.
     *
     * @param  User $user
     * @param  int  $month  1–12
     * @param  int  $year
     * @return array{
     *   user_id: int,
     *   month: int,
     *   year: int,
     *   base_salary: float,
     *   points_earned: int,
     *   points_bonus_amount: float,
     *   lateness_minutes: int,
     *   lateness_deduction_amount: float,
     *   manual_bonus_total: float,
     *   manual_deduction_total: float,
     *   unpaid_leave_days: int,
     *   unpaid_leave_deduction: float,
     *   net_salary: float,
     * }
     *
     * @throws \RuntimeException  When the user has no EmployeeProfile.
     */
    public function calculateForUser(User $user, int $month, int $year): array
    {
        // ── Step 1: Employee profile ──────────────────────────────────────────
        /** @var EmployeeProfile $profile */
        $profile = $user->employeeProfile;

        if ($profile === null) {
            throw new \RuntimeException(
                "User [{$user->id}] has no EmployeeProfile. Cannot calculate payroll."
            );
        }

        $baseSalary = (float) $profile->base_salary;

        // Minute rate derived from hourly_rate (used for lateness deductions).
        // Guard against a zero hourly_rate to avoid division by zero.
        $minuteRate = $profile->hourly_rate > 0
            ? (float) $profile->hourly_rate / 60
            : 0.0;

        // Daily rate derived from base_salary / working days in month.
        // Used for unpaid leave deductions.
        $workingDaysPerMonth = $profile->working_days_per_week
            ? round(($profile->working_days_per_week / 7) * Carbon::create($year, $month, 1)->daysInMonth)
            : 22;
        $dailyRate = $workingDaysPerMonth > 0 ? $baseSalary / $workingDaysPerMonth : 0.0;

        // ── Step 2: Dynamic point value from DB ───────────────────────────────
        $pointValue = (float) Setting::getValue('gamification_point_value', 50);

        // ── Step 3: Period boundaries ─────────────────────────────────────────
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd   = $periodStart->copy()->endOfMonth();

        // ── Step 4: Gamification bonus ────────────────────────────────────────
        $pointsEarned = (int) PointTransaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->sum('points');

        $pointsBonusAmount = max(0.0, round($pointsEarned * $pointValue, 2));

        // ── Step 5: Attendance lateness deduction ─────────────────────────────
        $latenessMinutes = (int) Attendance::where('user_id', $user->id)
            ->where('status', 'late')
            ->whereBetween('date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->sum('deduction_minutes');

        $latenessDeductionAmount = round($latenessMinutes * $minuteRate, 2);

        // ── Step 6: Manual payroll transactions ───────────────────────────────
        $transactions = PayrollTransaction::where('user_id', $user->id)
            ->whereBetween('transaction_date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->get();

        $manualBonusTotal     = round((float) $transactions->where('type', 'manual_bonus')->sum('amount'), 2);
        $manualDeductionTotal = round((float) $transactions->where('type', 'manual_deduction')->sum('amount'), 2);

        // ── Step 7: Unpaid leave deduction ────────────────────────────────────
        $unpaidLeaves = LeaveRequest::where('user_id', $user->id)
            ->where('type', 'unpaid')
            ->where('status', 'approved')
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhereBetween('end_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
            })
            ->get();

        $unpaidLeaveDays      = (int) $unpaidLeaves->sum(fn ($l) => $l->durationInDays());
        $unpaidLeaveDeduction = round($unpaidLeaveDays * $dailyRate, 2);

        // ── Step 8: Net salary ────────────────────────────────────────────────
        $netSalary = round(
            $baseSalary
            + $pointsBonusAmount
            + $manualBonusTotal
            - $latenessDeductionAmount
            - $manualDeductionTotal
            - $unpaidLeaveDeduction,
            2
        );

        return [
            'user_id'                    => $user->id,
            'month'                      => $month,
            'year'                       => $year,
            'base_salary'                => $baseSalary,
            'points_earned'              => $pointsEarned,
            'points_bonus_amount'        => $pointsBonusAmount,
            'lateness_minutes'           => $latenessMinutes,
            'lateness_deduction_amount'  => $latenessDeductionAmount,
            'manual_bonus_total'         => $manualBonusTotal,
            'manual_deduction_total'     => $manualDeductionTotal,
            'unpaid_leave_days'          => $unpaidLeaveDays,
            'unpaid_leave_deduction'     => $unpaidLeaveDeduction,
            'net_salary'                 => $netSalary,
        ];
    }

    /**
     * Calculate and persist (or refresh) a payslip for one employee.
     *
     * Uses updateOrCreate so the command is safely re-runnable.
     * Only draft payslips are overwritten — approved/paid records are
     * left untouched to protect finalised payroll.
     *
     * @param  User $user
     * @param  int  $month
     * @param  int  $year
     * @return Payslip
     */
    public function generateForUser(User $user, int $month, int $year): Payslip
    {
        // Check for an existing non-draft payslip — do not overwrite.
        $existing = Payslip::where('user_id', $user->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existing && ! $existing->isDraft()) {
            return $existing; // Approved/paid — hands off.
        }

        $data = $this->calculateForUser($user, $month, $year);

        // Only persist columns that exist in the payslips table.
        // The extended breakdown fields (manual_bonus_total, etc.) are
        // returned in the API response but stored in their source tables.
        $persistable = array_intersect_key($data, array_flip([
            'user_id',
            'month',
            'year',
            'base_salary',
            'points_earned',
            'points_bonus_amount',
            'lateness_minutes',
            'lateness_deduction_amount',
            'net_salary',
        ]));

        return Payslip::updateOrCreate(
            [
                'user_id' => $user->id,
                'month'   => $month,
                'year'    => $year,
            ],
            array_merge($persistable, ['status' => 'draft'])
        );
    }
}
