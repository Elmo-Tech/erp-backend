<?php

namespace App\Modules\Project\Models;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time entry scoped to the Project Execution layer.
 *
 * Uses the `project_time_entries` table. This is intentionally separate from
 * the polymorphic `time_entries` table used by App\Modules\TimeEntry.
 */
class ProjectTimeEntry extends Model
{
    protected $table = 'project_time_entries';

    protected $fillable = [
        'task_id',
        'user_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_manual',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'start_time'       => 'datetime',
            'end_time'         => 'datetime',
            'duration_minutes' => 'integer',
            'is_manual'        => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The execution-layer task this entry is logged against.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    /**
     * The user who logged this time entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Whether the timer is still running (no end_time yet).
     */
    public function isRunning(): bool
    {
        return $this->end_time === null;
    }

    /**
     * Stop the timer and compute duration_minutes.
     */
    public function stop(): void
    {
        if ($this->isRunning()) {
            $end = now();
            $this->update([
                'end_time'         => $end,
                'duration_minutes' => (int) $this->start_time->diffInMinutes($end),
            ]);
        }
    }
}
