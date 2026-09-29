<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShortPermission extends Model
{
    use HasCreatedUpdatedBy;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'permission_date',
        'start_time',
        'end_time',
        'duration_minutes',
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
            'permission_date' => 'date',
            'duration_minutes' => 'integer',
            'is_paid' => 'boolean',
            'reviewed_at' => 'datetime',
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
}
