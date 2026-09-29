<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentContract extends Model
{
    use HasCreatedUpdatedBy;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'employee_id',
        'employment_type',
        'base_salary',
        'start_date',
        'end_date',
        'terminated_on',
        'duration_months',
        'work_days',
        'work_hours_per_day',
        'work_start_time',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'terminated_on' => 'date',
            'duration_months' => 'integer',
            'work_days' => 'array',
            'work_hours_per_day' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
