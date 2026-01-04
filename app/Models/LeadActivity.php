<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'title',
        'description',
        'outcome',
        'scheduled_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
