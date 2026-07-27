<?php

namespace App\Models;

use App\Enums\TimerStopReason;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TimeEntry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'trackable_id',
        'trackable_type',
        'start_time',
        'last_heartbeat_at',
        'end_time',
        'duration_minutes',
        'stop_reason',
        'resumed_from_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'end_time' => 'datetime',
            'duration_minutes' => 'integer',
            'stop_reason' => TimerStopReason::class,
        ];
    }

    /**
     * Get the user that owns this time entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trackable entity (Lead, Quotation, Task, etc.).
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if this time entry is currently active (no end time).
     */
    public function isActive(): bool
    {
        return $this->end_time === null;
    }

    public function startedAt(): Carbon
    {
        return $this->asDateTime($this->getRawOriginal('start_time'));
    }

    public function heartbeatAt(): ?Carbon
    {
        $heartbeat = $this->getRawOriginal('last_heartbeat_at');

        return $heartbeat === null ? null : $this->asDateTime($heartbeat);
    }

    public function stopReason(): ?TimerStopReason
    {
        $reason = $this->getAttribute('stop_reason');

        if ($reason instanceof TimerStopReason) {
            return $reason;
        }

        return is_string($reason) ? TimerStopReason::tryFrom($reason) : null;
    }
}
