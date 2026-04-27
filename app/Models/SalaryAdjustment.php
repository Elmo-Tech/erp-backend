<?php

namespace App\Models;

use App\Modules\User\Models\User;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryAdjustment extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'adjustment_type',
        'reference_type',
        'reference_id',
        'amount',
        'reason',
        'adjustment_date',
        'payroll_month',
        'payroll_year',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'adjustment_date' => 'date',
        'payroll_month' => 'integer',
        'payroll_year' => 'integer',
        'approved_at' => 'datetime',
    ];

    /**
     * Adjustment types
     */
    const TYPE_DEDUCTION = 'deduction';

    const TYPE_REWARD = 'reward';

    const TYPE_BONUS = 'bonus';

    const TYPE_OVERTIME = 'overtime';

    const TYPE_ALLOWANCE = 'allowance';

    const TYPE_PENALTY = 'penalty';

    const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_PROCESSED = 'processed';

    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the employee (user)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the related entity (deduction, reward, etc.)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for deductions
     */
    public function scopeDeductions($query)
    {
        return $query->where('adjustment_type', self::TYPE_DEDUCTION);
    }

    /**
     * Scope for rewards
     */
    public function scopeRewards($query)
    {
        return $query->where('adjustment_type', self::TYPE_REWARD);
    }

    /**
     * Scope for specific payroll period
     */
    public function scopeForPayrollPeriod($query, int $year, int $month)
    {
        return $query->where('payroll_year', $year)
            ->where('payroll_month', $month);
    }

    /**
     * Scope for pending adjustments
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for processed adjustments
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    /**
     * Check if adjustment is positive (adds to salary)
     */
    public function isPositive(): bool
    {
        return in_array($this->adjustment_type, [
            self::TYPE_REWARD,
            self::TYPE_BONUS,
            self::TYPE_OVERTIME,
            self::TYPE_ALLOWANCE,
        ]);
    }

    /**
     * Check if adjustment is negative (subtracts from salary)
     */
    public function isNegative(): bool
    {
        return in_array($this->adjustment_type, [
            self::TYPE_DEDUCTION,
            self::TYPE_PENALTY,
        ]);
    }

    /**
     * Get signed amount (positive for additions, negative for deductions)
     */
    public function getSignedAmountAttribute(): float
    {
        return $this->isNegative() ? -$this->amount : $this->amount;
    }

    /**
     * Approve the adjustment
     */
    public function approve(int $approverId, ?string $notes = null): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        return true;
    }

    /**
     * Mark as processed (included in payroll)
     */
    public function markAsProcessed(): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_PROCESSED,
        ]);

        return true;
    }

    /**
     * Cancel the adjustment
     */
    public function cancel(string $reason): bool
    {
        if ($this->status === self::STATUS_PROCESSED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'notes' => 'Cancelled: '.$reason,
        ]);

        return true;
    }

    /**
     * Create from employee deduction
     */
    public static function createFromDeduction(EmployeeDeduction $deduction): self
    {
        return self::create([
            'user_id' => $deduction->user_id,
            'adjustment_type' => self::TYPE_DEDUCTION,
            'reference_type' => EmployeeDeduction::class,
            'reference_id' => $deduction->id,
            'amount' => $deduction->amount,
            'reason' => $deduction->reason,
            'adjustment_date' => $deduction->deduction_date,
            'payroll_month' => $deduction->deduction_date->month,
            'payroll_year' => $deduction->deduction_date->year,
            'status' => $deduction->status === EmployeeDeduction::STATUS_APPROVED
                ? self::STATUS_APPROVED
                : self::STATUS_PENDING,
            'approved_by' => $deduction->approved_by,
            'approved_at' => $deduction->approved_at,
        ]);
    }

    /**
     * Create from employee reward
     */
    public static function createFromReward(EmployeeReward $reward): self
    {
        return self::create([
            'user_id' => $reward->user_id,
            'adjustment_type' => self::TYPE_REWARD,
            'reference_type' => EmployeeReward::class,
            'reference_id' => $reward->id,
            'amount' => $reward->amount,
            'reason' => $reward->reason,
            'adjustment_date' => $reward->reward_date,
            'payroll_month' => $reward->reward_date->month,
            'payroll_year' => $reward->reward_date->year,
            'status' => $reward->status === EmployeeReward::STATUS_APPROVED
                ? self::STATUS_APPROVED
                : self::STATUS_PENDING,
            'approved_by' => $reward->approved_by,
            'approved_at' => $reward->approved_at,
        ]);
    }
}
