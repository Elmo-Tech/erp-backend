<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePermissionAllowance extends Model
{
    use HasCreatedUpdatedBy;

    protected $fillable = [
        'employee_id',
        'period',
        'allocated_minutes',
        'used_paid_minutes',
        'policy_version_id',
    ];

    protected function casts(): array
    {
        return [
            'allocated_minutes' => 'integer',
            'used_paid_minutes' => 'integer',
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
}
