<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EmployeeAdvanceSettlement extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'employee_advance_id',
        'sequence',
        'due_on',
        'scheduled_amount',
        'status',
        'settled_by',
        'settled_at',
        'settlement_reference',
        'salary_adjustment_id',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_on' => 'date',
            'scheduled_amount' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function salaryAdjustment(): BelongsTo
    {
        return $this->belongsTo(SalaryAdjustment::class, 'salary_adjustment_id');
    }

    public function salaryAdjustmentReference(): MorphOne
    {
        return $this->morphOne(SalaryAdjustment::class, 'reference');
    }
}
