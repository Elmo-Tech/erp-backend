<?php

namespace App\Modules\HR\Models;

use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $shift_id
 * @property float $base_salary
 * @property float $hourly_rate
 * @property Carbon $join_date
 * @property-read User        $user
 * @property-read Shift|null  $shift
 */
class EmployeeProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'shift_id',
        'base_salary',
        'hourly_rate',
        'join_date',
        'grace_days_balance',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'join_date' => 'date',
            'grace_days_balance' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The user this profile belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The shift assigned to this employee.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    // -------------------------------------------------------------------------
    // Gamification helpers
    // -------------------------------------------------------------------------

    /**
     * Calculate the employee's active points balance for the current 2-month cycle.
     *
     * The CTO's requirement: payouts are distributed every 2 months.
     * The cycle is calculated as:
     *   - Odd months  (Jan, Mar, May, Jul, Sep, Nov): cycle starts on the 1st of that month.
     *   - Even months (Feb, Apr, Jun, Aug, Oct, Dec): cycle started on the 1st of the previous month.
     *
     * Example: if today is May 15 → cycle start = May 1.
     *          if today is Jun 20 → cycle start = May 1.
     *          if today is Jul 5  → cycle start = Jul 1.
     *
     * @return int Sum of all point transactions in the current 2-month cycle.
     *             Returns 0 if the user has no transactions in this period.
     */
    public function calculateActivePoints(): int
    {
        $now = Carbon::now();

        // Determine the start of the current 2-month cycle.
        // Odd month = cycle start; even month = previous (odd) month is the start.
        $cycleStartMonth = $now->month % 2 === 1
            ? $now->month          // odd month: cycle starts this month
            : $now->month - 1;     // even month: cycle started last month

        $cycleStart = Carbon::create($now->year, $cycleStartMonth, 1)->startOfDay();
        $cycleEnd = $cycleStart->copy()->addMonths(2)->subSecond(); // end of 2-month window

        return (int) PointTransaction::where('user_id', $this->user_id)
            ->whereBetween('created_at', [$cycleStart, $cycleEnd])
            ->sum('points');
    }
}
