<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'late_minutes',
        'idle_minutes',
        'deduction_minutes',
        'used_grace_day',
        'expected_clock_in',
        'expected_clock_out',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'late_minutes' => 'integer',
            'idle_minutes' => 'integer',
            'deduction_minutes' => 'integer',
            'used_grace_day' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this attendance record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate late minutes based on expected clock-in time.
     * 
     * @return int Minutes late (0 if on time or early)
     */
    public function calculateLateMinutes(): int
    {
        if (!$this->expected_clock_in || !$this->clock_in) {
            return 0;
        }

        $expectedTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->expected_clock_in);
        $actualTime = Carbon::parse($this->clock_in);

        if ($actualTime->lte($expectedTime)) {
            return 0;
        }

        return $expectedTime->diffInMinutes($actualTime);
    }

    /**
     * Calculate deduction minutes based on lateness rules.
     * 
     * Rules:
     * - If late <= 30 minutes: No deduction
     * - If late > 30 minutes AND has grace days: Use grace day, no deduction
     * - If late > 30 minutes AND no grace days: Deduct in 30-minute increments
     *   (e.g., 35 min late = 30 min deduction, 65 min late = 60 min deduction)
     * 
     * @param User $user The user to check grace days for
     * @return array{deduction_minutes: int, used_grace_day: bool}
     */
    public function calculateDeduction(User $user): array
    {
        $lateMinutes = $this->late_minutes ?? $this->calculateLateMinutes();
        
        // Not late enough to matter
        if ($lateMinutes <= 30) {
            return ['deduction_minutes' => 0, 'used_grace_day' => false];
        }

        // Check if user has grace days remaining
        if ($user->hasGraceDaysRemaining()) {
            return ['deduction_minutes' => 0, 'used_grace_day' => true];
        }

        // Calculate deduction in 30-minute increments
        // 31-60 min = 30 min deduction, 61-90 min = 60 min deduction, etc.
        $deductionMinutes = floor($lateMinutes / 30) * 30;
        
        return ['deduction_minutes' => (int) $deductionMinutes, 'used_grace_day' => false];
    }

    /**
     * Calculate idle minutes for this attendance record.
     * 
     * Formula: (clock_out - clock_in in minutes) - Sum(TimeEntry duration_minutes for user on that date)
     * Returns 0 if clock_out is not set or if calculation results in negative value.
     *
     * @return int
     */
    public function calculateIdleMinutes(): int
    {
        // Cannot calculate idle time without clock_out
        if ($this->clock_out === null) {
            return 0;
        }

        // Calculate total minutes between clock_in and clock_out
        $totalMinutes = $this->clock_in->diffInMinutes($this->clock_out);

        // Sum all completed time entries for this user on this date
        $workedMinutes = TimeEntry::where('user_id', $this->user_id)
            ->whereDate('start_time', $this->date)
            ->whereNotNull('end_time')
            ->sum('duration_minutes');

        // Return max(0, total - worked) to avoid negative idle time
        return max(0, $totalMinutes - $workedMinutes);
    }

    /**
     * Get total worked minutes from time entries
     */
    public function getWorkedMinutes(): int
    {
        return TimeEntry::where('user_id', $this->user_id)
            ->whereDate('start_time', $this->date)
            ->whereNotNull('end_time')
            ->sum('duration_minutes');
    }

    /**
     * Check if user is currently clocked in (no clock_out)
     */
    public function isClockedIn(): bool
    {
        return $this->clock_in !== null && $this->clock_out === null;
    }
}
