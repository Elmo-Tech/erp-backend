<?php

namespace App\Modules\SOP\Models;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCheckin extends Model
{
    protected $fillable = [
        'user_id',
        'directive_id',
        'focus_items',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'focus_items' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function directive(): BelongsTo
    {
        return $this->belongsTo(Directive::class);
    }
}
