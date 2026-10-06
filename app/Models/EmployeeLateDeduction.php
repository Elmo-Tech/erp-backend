<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use LogicException;

class EmployeeLateDeduction extends Model
{
    use HasCreatedUpdatedBy;

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_WAIVED = 'waived';

    public const STATUS_OVERRIDDEN = 'overridden';

    protected $fillable = [
        'employee_id',
        'attendance_id',
        'policy_version_id',
        'policy_snapshot',
        'attendance_date',
        'original_late_minutes',
        'eligible_late_minutes',
        'occurrence_number',
        'tier_minimum_minutes',
        'tier_maximum_minutes',
        'percentage',
        'daily_rate',
        'calculated_amount',
        'override_amount',
        'override_reason',
        'status',
        'waiver_reason',
        'salary_adjustment_id',
        'evaluated_by',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'policy_snapshot' => 'array',
            'attendance_date' => 'date',
            'original_late_minutes' => 'integer',
            'eligible_late_minutes' => 'integer',
            'occurrence_number' => 'integer',
            'tier_minimum_minutes' => 'integer',
            'tier_maximum_minutes' => 'integer',
            'percentage' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'calculated_amount' => 'decimal:2',
            'override_amount' => 'decimal:2',
            'evaluated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $deduction): void {
            $allowedChanges = [
                'salary_adjustment_id',
                'override_amount',
                'override_reason',
                'status',
                'waiver_reason',
                'updated_by',
                'updated_at',
            ];

            if (array_diff(array_keys($deduction->getDirty()), $allowedChanges) !== []) {
                throw new LogicException('Late deduction calculation fields are immutable after evaluation.');
            }

            if ($deduction->isDirty('salary_adjustment_id')
                && $deduction->getOriginal('salary_adjustment_id') !== null) {
                throw new LogicException('The linked salary adjustment cannot be replaced.');
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(HrPolicy::class, 'policy_version_id');
    }

    public function salaryAdjustment(): BelongsTo
    {
        return $this->belongsTo(SalaryAdjustment::class, 'salary_adjustment_id');
    }

    public function salaryAdjustmentReference(): MorphOne
    {
        return $this->morphOne(SalaryAdjustment::class, 'reference');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
