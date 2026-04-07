<?php

namespace App\Models;

use App\Enums\PointTransactionType;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointTransaction extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'user_id',
        'points',
        'type',
        'source_type',
        'source_id',
        'reason',
        'balance_after',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'balance_after' => 'integer',
            'type' => PointTransactionType::class,
        ];
    }

    /**
     * Get the period this transaction belongs to
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PointPeriod::class, 'period_id');
    }

    /**
     * Get the user that owns this transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the source entity (Task, CodeReview, Attendance, etc.)
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    /**
     * Scope for positive transactions (points earned)
     */
    public function scopeEarned($query)
    {
        return $query->where('points', '>', 0);
    }

    /**
     * Scope for negative transactions (points deducted)
     */
    public function scopeDeducted($query)
    {
        return $query->where('points', '<', 0);
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType($query, PointTransactionType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for date range
     */
    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
