<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeReward extends Model
{
    use HasFactory, HasCreatedUpdatedBy, SoftDeletes;

    protected $fillable = [
        'user_id',
        'reward_type_id',
        'reward_date',
        'amount',
        'reason',
        'status',
        'points_used',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'paid_at',
        'payment_reference',
        'rewardable_type',
        'rewardable_id',
    ];

    protected $casts = [
        'reward_date' => 'date',
        'amount' => 'decimal:2',
        'points_used' => 'integer',
        'approved_at' => 'datetime',
        'paid_at' => 'date',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the employee (user) who received the reward
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reward type
     */
    public function rewardType(): BelongsTo
    {
        return $this->belongsTo(RewardType::class);
    }

    /**
     * Get the approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the related entity (project, task, evaluation, etc.)
     */
    public function rewardable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for pending rewards
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved rewards
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for paid rewards
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope for specific month
     */
    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('reward_date', $year)
            ->whereMonth('reward_date', $month);
    }

    /**
     * Check if reward can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Approve the reward
     */
    public function approve(int $approverId): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Reject the reward
     */
    public function reject(int $approverId, string $reason): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return true;
    }

    /**
     * Mark reward as paid
     */
    public function markAsPaid(string $paymentReference = null): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
        ]);

        return true;
    }
}
