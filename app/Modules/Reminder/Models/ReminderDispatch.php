<?php

namespace App\Modules\Reminder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReminderDispatch extends Model
{
    protected $fillable = [
        'reminder_key',
        'reminder_type',
        'remindable_type',
        'remindable_id',
        'notifiable_type',
        'notifiable_id',
        'channel',
        'scheduled_for',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
