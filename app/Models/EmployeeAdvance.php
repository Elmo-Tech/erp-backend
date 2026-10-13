<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdvance extends Model
{
    use HasCreatedUpdatedBy;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DISBURSED = 'disbursed';

    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'employee_id',
        'requested_amount',
        'approved_amount',
        'disbursed_amount',
        'status',
        'reason',
        'installment_count',
        'is_salary_withdrawal',
        'policy_version_id',
        'policy_snapshot',
        'salary_at_approval',
        'advance_limit_amount',
        'approved_by',
        'approved_at',
        'review_note',
        'disbursed_by',
        'disbursed_at',
        'disbursement_reference',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'disbursed_amount' => 'decimal:2',
            'installment_count' => 'integer',
            'is_salary_withdrawal' => 'boolean',
            'policy_snapshot' => 'array',
            'salary_at_approval' => 'decimal:2',
            'advance_limit_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(HrPolicy::class, 'policy_version_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceSettlement::class)->orderBy('sequence');
    }

    public function getOutstandingAmountAttribute(): float
    {
        if ($this->disbursed_amount === null) {
            return 0.0;
        }

        $settlements = $this->relationLoaded('settlements')
            ? $this->settlements
            : $this->settlements()->where('status', EmployeeAdvanceSettlement::STATUS_SCHEDULED)->get();

        return round((float) $settlements
            ->where('status', EmployeeAdvanceSettlement::STATUS_SCHEDULED)
            ->sum('scheduled_amount'), 2);
    }
}
