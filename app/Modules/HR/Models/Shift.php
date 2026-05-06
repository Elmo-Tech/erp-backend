<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int    $id
 * @property string $name
 * @property string $start_time          HH:MM:SS
 * @property string $end_time            HH:MM:SS
 * @property array  $working_days        e.g. ["Sun","Mon","Tue","Wed","Thu"]
 * @property int    $grace_period_minutes
 */
class Shift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'working_days',
        'grace_period_minutes',
    ];

    protected function casts(): array
    {
        return [
            'working_days'         => 'array',
            'grace_period_minutes' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Employees assigned to this shift.
     */
    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the deadline (start_time + grace_period_minutes) as a Carbon
     * instance anchored to today's date.
     */
    public function latenessDeadlineToday(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::today()
            ->setTimeFromTimeString($this->start_time)
            ->addMinutes($this->grace_period_minutes);
    }
}
