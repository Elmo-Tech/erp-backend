<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

class HrPolicy extends Model
{
    protected $fillable = [
        'settings',
        'effective_from',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'effective_from' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('HR policy versions are immutable. Create a new version instead.');
        });

        static::deleting(function (): void {
            throw new LogicException('HR policy versions cannot be deleted.');
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Limit policies to versions active on the specified date.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEffectiveOn(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date);
    }
}
