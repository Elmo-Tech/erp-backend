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
}
