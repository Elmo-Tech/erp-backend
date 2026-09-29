<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasCreatedUpdatedBy;

    protected $fillable = [
        'name',
        'slug',
        'annual_allowance_days',
        'is_paid',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'annual_allowance_days' => 'integer',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function employeeLeaveBalances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    public function employeeLeaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
