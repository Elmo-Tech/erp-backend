<?php

namespace App\Modules\PersonalTasks\Models;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalTimeEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'personal_task_id',
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
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_minutes' => 'integer',
            'is_manual' => 'boolean',
        ];
    }

    public function personalTask(): BelongsTo
    {
        return $this->belongsTo(PersonalTask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return $this->end_time === null;
    }

    public function stop(): void
    {
        if (! $this->isRunning()) {
            return;
        }

        $end = now();
        $this->update([
            'end_time' => $end,
            'duration_minutes' => (int) $this->start_time->diffInMinutes($end),
        ]);
    }
}
