<?php

namespace App\Modules\Attendance\Models;

use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property Carbon|null $clock_in
 * @property Carbon|null $clock_out
 * @property string $status present|absent|late|excused
 * @property int $total_worked_minutes
 * @property int $late_minutes
 * @property bool $is_late
 * @property int $idle_minutes
 * @property int $deduction_minutes
 * @property bool $used_grace_day
 * @property string|null $expected_clock_in
 * @property string|null $expected_clock_out
 * @property string|null $notes
 * @property-read User   $user
 */
class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'total_worked_minutes',
        'late_minutes',
        'is_late',
        'idle_minutes',
        'deduction_minutes',
        'used_grace_day',
        'expected_clock_in',
        'expected_clock_out',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'late_minutes' => 'integer',
            'is_late' => 'boolean',
            'idle_minutes' => 'integer',
            'deduction_minutes' => 'integer',
            'total_worked_minutes' => 'integer',
            'used_grace_day' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The user this attendance record belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Business Logic Helpers
    // -------------------------------------------------------------------------

    /**
     * Calculate late minutes based on expected clock-in time.
     *
     * @return int Minutes late (0 if on time or early)
     */
    public function calculateLateMinutes(): int
    {
        if (! $this->expected_clock_in || ! $this->clock_in) {
            return 0;
        }

        $expectedTime = Carbon::parse($this->date->format('Y-m-d').' '.$this->expected_clock_in);
        $actualTime = Carbon::parse($this->clock_in);

        if ($actualTime->lte($expectedTime)) {
            return 0;
        }

        return (int) $expectedTime->diffInMinutes($actualTime);
    }

    /**
     * Calculate deduction minutes based on lateness rules.
     *
     * Rules (threshold-based):
     * - If late <= threshold minutes: No deduction
     * - If late > threshold AND has grace days: Use grace day, no deduction
     * - If late > threshold AND no grace days: Deduct in threshold-minute increments
     *
     * @return array{deduction_minutes: int, used_grace_day: bool}
     */
    public function calculateDeduction(User $user): array
    {
        $lateMinutes = $this->late_minutes ?? $this->calculateLateMinutes();
        $lateThreshold = (int) config('attendance.late_threshold_minutes', 15);

        if ($lateMinutes <= $lateThreshold) {
            return ['deduction_minutes' => 0, 'used_grace_day' => false];
        }

        if ($user->hasGraceDaysRemaining()) {
            return ['deduction_minutes' => 0, 'used_grace_day' => true];
        }

        $deductionMinutes = (int) (floor($lateMinutes / $lateThreshold) * $lateThreshold);

        return ['deduction_minutes' => $deductionMinutes, 'used_grace_day' => false];
    }

    /**
     * Calculate idle minutes for this attendance record.
     *
     * Formula: (clock_out - clock_in) - sum(time_entry duration_minutes for user on date)
     */
    public function calculateIdleMinutes(): int
    {
        if ($this->clock_out === null) {
            return 0;
        }

        $totalMinutes = $this->clock_in->diffInMinutes($this->clock_out);
        $workedMinutes = $this->getWorkedMinutes();

        return max(0, $totalMinutes - $workedMinutes);
    }

    /**
     * Sum of completed time-entry minutes for this user on this date.
     */
    public function getWorkedMinutes(): int
    {
        $projectMinutes = ProjectTimeEntry::where('user_id', $this->user_id)
            ->whereDate('start_time', $this->date)
            ->whereNotNull('end_time')
            ->sum('duration_minutes');

        $personalMinutes = PersonalTimeEntry::where('user_id', $this->user_id)
            ->whereDate('start_time', $this->date)
            ->whereNotNull('end_time')
            ->sum('duration_minutes');

        return (int) $projectMinutes + (int) $personalMinutes;
    }

    public function getExpectedWorkedMinutes(): int
    {
        if (! $this->expected_clock_in || ! $this->expected_clock_out) {
            return 0;
        }

        $start = Carbon::parse($this->date->format('Y-m-d').' '.$this->expected_clock_in);
        $end = Carbon::parse($this->date->format('Y-m-d').' '.$this->expected_clock_out);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Whether the user is currently clocked in (no clock_out yet).
     */
    public function isClockedIn(): bool
    {
        return $this->clock_in !== null && $this->clock_out === null;
    }
}
