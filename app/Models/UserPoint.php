<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\CreatedUpdatedBy;

class UserPoint extends Model
{
    use HasFactory, CreatedUpdatedBy;

    protected $fillable = [
        'user_id',
        'points_change',
        'points_before',
        'points_after',
        'reason',
        'type',
    ];

    protected $casts = [
        'points_change' => 'integer',
        'points_before' => 'integer',
        'points_after' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the point record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who created this point record.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this point record.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get points for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get points within a date range.
     */
    public function scopeInDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get manual point changes only.
     */
    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }

    /**
     * Scope to get automatic point changes only.
     */
    public function scopeAutomatic($query)
    {
        return $query->where('type', 'automatic');
    }
}