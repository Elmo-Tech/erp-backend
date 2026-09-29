<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EmployeeOvertimeRequest extends Model
{
    use HasCreatedUpdatedBy;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'overtime_date',
        'start_time',
        'end_time',
        'requested_minutes',
        'actual_minutes',
        'approved_minutes',
        'status',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'policy_version_id',
        'hourly_rate',
        'overtime_multiplier',
        'payable_amount',
    ];

    protected function casts(): array
    {
        return [
            'overtime_date' => 'date',
            'requested_minutes' => 'integer',
            'actual_minutes' => 'integer',
            'approved_minutes' => 'integer',
            'reviewed_at' => 'datetime',
            'hourly_rate' => 'decimal:4',
            'overtime_multiplier' => 'decimal:4',
            'payable_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(HrPolicy::class, 'policy_version_id');
    }

    public function salaryAdjustment(): MorphOne
    {
        return $this->morphOne(SalaryAdjustment::class, 'reference');
    }

    public function getUnapprovedMinutesAttribute(): ?int
    {
        if ($this->actual_minutes === null) {
            return null;
        }

        return max(0, $this->actual_minutes - ($this->approved_minutes ?? 0));
    }
}
