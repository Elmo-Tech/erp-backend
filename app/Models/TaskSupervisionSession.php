<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSupervisionSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'supervisor_id',
        'help_request_id',
        'session_id',
        'notes',
        'started_at',
        'last_heartbeat_at',
        'ended_at',
        'duration_minutes',
        'stop_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(TaskHelpRequest::class, 'help_request_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function currentDurationMinutes(): ?int
    {
        if ($this->duration_minutes !== null) {
            return $this->duration_minutes;
        }

        if ($this->started_at === null) {
            return null;
        }

        return max(0, (int) floor($this->started_at->diffInSeconds(now(), false) / 60));
    }
}
