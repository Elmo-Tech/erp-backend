<?php

namespace App\Models;

use App\Modules\User\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'closed_at',
        'closed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who closed this period
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get all transactions in this period
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'period_id');
    }

    /**
     * Get the currently active period
     */
    public static function getActivePeriod(): ?self
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Get total points for a user in this period
     */
    public function getUserPoints(int $userId): int
    {
        return $this->transactions()
            ->where('user_id', $userId)
            ->sum('points');
    }

    /**
     * Get leaderboard for this period
     */
    public function getLeaderboard(int $limit = 10): array
    {
        return $this->transactions()
            ->selectRaw('user_id, SUM(points) as total_points')
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderByDesc('total_points')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'userId' => $item->user_id,
                'name' => $item->user->name,
                'email' => $item->user->email,
                'points' => $item->total_points,
            ])
            ->toArray();
    }

    /**
     * Check if period is closed
     */
    public function isClosed(): bool
    {
        return ! $this->is_active && $this->closed_at !== null;
    }

    /**
     * Scope for active periods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for closed periods
     */
    public function scopeClosed($query)
    {
        return $query->where('is_active', false)->whereNotNull('closed_at');
    }
}
