<?php

namespace App\Modules\Activity\Models;

use App\Modules\User\Models\User;

use App\Enums\ReminderType;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityReminder extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'user_id',
        'type',
        'minutes_offset',
        'remind_at',
        'channel',
        'is_sent',
        'sent_at',
        'title',
        'message',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
            'is_sent' => 'boolean',
            'minutes_offset' => 'integer',
        ];
    }

    /**
     * Get the remindable entity (Activity, Meeting, Proposal, Quotation)
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user to remind
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pending reminders
     */
    public function scopePending($query)
    {
        return $query->where('is_sent', false)
            ->where('remind_at', '<=', now());
    }

    /**
     * Scope for upcoming reminders
     */
    public function scopeUpcoming($query, int $minutes = 60)
    {
        return $query->where('is_sent', false)
            ->whereBetween('remind_at', [now(), now()->addMinutes($minutes)]);
    }

    /**
     * Mark reminder as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'is_sent' => true,
            'sent_at' => now(),
        ]);
    }
}
