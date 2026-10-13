<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $deduction_type_id
 * @property Carbon|null $deduction_date
 * @property float|int|string $amount
 * @property string|null $reason
 * @property string $status
 * @property string|null $source
 * @property int|null $attendance_id
 * @property int|null $late_minutes
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property string $deductible_type
 * @property int $deductible_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read DeductionType|null $deductionType
 * @property-read Attendance|null $attendance
 * @property-read User|null $approver
 */
class EmployeeDeduction extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'deduction_type_id',
        'deduction_date',
        'occurred_at',
        'amount',
        'reason',
        'status',
        'source',
        'attendance_id',
        'late_minutes',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'deductible_type',
        'deductible_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deduction_date' => 'date',
        'occurred_at' => 'datetime',
        'amount' => 'decimal:2',
        'late_minutes' => 'integer',
        'approved_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    const SOURCE_DIRECT_MANUAL = 'direct_manual';

    /**
     * Get the employee (user) who received the deduction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the deduction type
     */
    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
    }

    /**
     * Get the attendance record (if deduction is from attendance)
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * Get the approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the related entity (project, task, etc.)
     */
    public function deductible(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the salary adjustment created for this deduction.
     */
    public function salaryAdjustment(): MorphOne
    {
        return $this->morphOne(SalaryAdjustment::class, 'reference');
    }

    /**
     * Scope for pending deductions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved deductions
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for specific month
     */
    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('deduction_date', $year)
            ->whereMonth('deduction_date', $month);
    }

    /**
     * Check if deduction can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check whether this is a direct manager-entered deduction.
     */
    public function isDirectManual(): bool
    {
        return $this->source === self::SOURCE_DIRECT_MANUAL;
    }

    /**
     * Approve the deduction
     */
    public function approve(int $approverId): bool
    {
        if (! $this->canBeApproved()) {
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
     * Reject the deduction
     */
    public function reject(int $approverId, string $reason): bool
    {
        if (! $this->canBeApproved()) {
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
}
