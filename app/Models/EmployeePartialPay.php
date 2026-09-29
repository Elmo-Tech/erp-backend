<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EmployeePartialPay extends Model
{
    use HasCreatedUpdatedBy;

    public const KIND_FIRST_MONTH = 'first_month';

    public const KIND_TERMINATION = 'termination';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'employment_contract_id',
        'kind',
        'payroll_year',
        'payroll_month',
        'service_start_date',
        'service_end_date',
        'payable_days',
        'base_salary_snapshot',
        'daily_rate_snapshot',
        'gross_amount',
        'policy_version_id',
        'policy_snapshot',
        'contract_work_days',
        'status',
        'approved_by',
        'approved_at',
        'review_note',
        'salary_adjustment_id',
    ];

    protected function casts(): array
    {
        return [
            'payroll_year' => 'integer',
            'payroll_month' => 'integer',
            'service_start_date' => 'date',
            'service_end_date' => 'date',
            'payable_days' => 'integer',
            'base_salary_snapshot' => 'decimal:2',
            'daily_rate_snapshot' => 'decimal:6',
            'gross_amount' => 'decimal:2',
            'policy_snapshot' => 'array',
            'contract_work_days' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $partialPay): void {
            $financialInputs = [
                'employee_id',
                'employment_contract_id',
                'kind',
                'payroll_year',
                'payroll_month',
                'service_start_date',
                'service_end_date',
                'payable_days',
                'base_salary_snapshot',
                'daily_rate_snapshot',
                'gross_amount',
                'policy_version_id',
                'policy_snapshot',
                'contract_work_days',
            ];

            if ($partialPay->isDirty($financialInputs)) {
                throw new LogicException('Employee partial pay financial inputs are immutable.');
            }

            if (
                $partialPay->isDirty('salary_adjustment_id')
                && $partialPay->getOriginal('salary_adjustment_id') !== null
            ) {
                throw new LogicException('Employee partial pay salary adjustment link is immutable.');
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function employmentContract(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class);
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(HrPolicy::class, 'policy_version_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function salaryAdjustment(): BelongsTo
    {
        return $this->belongsTo(SalaryAdjustment::class);
    }
}
