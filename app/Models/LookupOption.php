<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LookupOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'value',
        'label',
        'color',
        'icon',
        'metadata',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope a query to only include options of a given type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include active options.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Get all options of a specific type.
     */
    public static function getByType(string $type)
    {
        return static::ofType($type)->ordered()->get();
    }

    /**
     * Get active options of a specific type.
     */
    public static function getActiveByType(string $type)
    {
        return static::ofType($type)->active()->ordered()->get();
    }
}
