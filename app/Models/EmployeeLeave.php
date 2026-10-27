<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    use HasCreatedUpdatedBy;

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'working_days',
        'is_paid',
        'status',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'policy_version_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'working_days' => 'decimal:2',
            'is_paid' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function needsReview(): bool
    {
        return $this->status === null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(HrPolicy::class, 'policy_version_id');
    }
}
