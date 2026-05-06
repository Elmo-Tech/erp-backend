<?php

namespace App\Modules\Calendar\Models;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use SoftDeletes;

    protected $table = 'calendar_events';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'event_type',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time'   => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
