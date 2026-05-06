<?php

namespace App\Modules\HR\Services;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Models\Payslip;
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
 *   net_salary = base_salary + points_bonus_amount - lateness_deduction_amount
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

        // ── Step 2: Dynamic point value from DB ───────────────────────────────
        // The CTO requirement: point value MUST come from the database, not
        // a hardcoded config. Setting::getValue() caches the result for 1 hour
        // so batch runs don't hammer the DB.
        $pointValue = (float) Setting::getValue('gamification_point_value', 50);

        // ── Step 3: Gamification bonus ────────────────────────────────────────
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd   = $periodStart->copy()->endOfMonth();

        $pointsEarned = (int) PointTransaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->sum('points');

        // Points can be negative (deductions); bonus floor is 0 — we don't
        // penalise the base salary via the points channel (separate deduction
        // fields exist for that if the business requires it later).
        $pointsBonusAmount = max(0.0, round($pointsEarned * $pointValue, 2));

        // ── Step 4: Attendance lateness deduction ─────────────────────────────
        // Sum the deduction_minutes column for all late records in the period.
        // deduction_minutes is already threshold-adjusted by the attendance
        // engine (grace days, threshold rounding) — we trust that value here.
        $latenessMinutes = (int) Attendance::where('user_id', $user->id)
            ->where('status', 'late')
            ->whereBetween('date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->sum('deduction_minutes');

        $latenessDeductionAmount = round($latenessMinutes * $minuteRate, 2);

        // ── Step 5: Net salary ────────────────────────────────────────────────
        $netSalary = round(
            $baseSalary + $pointsBonusAmount - $latenessDeductionAmount,
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

        return Payslip::updateOrCreate(
            [
                'user_id' => $user->id,
                'month'   => $month,
                'year'    => $year,
            ],
            array_merge($data, ['status' => 'draft'])
        );
    }
}
