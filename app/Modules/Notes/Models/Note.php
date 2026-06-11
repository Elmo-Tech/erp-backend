<?php

namespace App\Modules\Notes\Models;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Note extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'body',
        'noted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'noted_at' => 'datetime',
        ];
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
