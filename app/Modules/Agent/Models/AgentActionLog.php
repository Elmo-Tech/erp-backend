<?php

namespace App\Modules\Agent\Models;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentActionLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'status',
        'payload',
        'preview',
        'result',
        'error',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'preview' => 'array',
            'result' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
