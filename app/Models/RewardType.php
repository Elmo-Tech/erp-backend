<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RewardType extends Model
{
    use HasFactory, HasCreatedUpdatedBy, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'calculation_type',
        'base_amount',
        'is_active',
        'requires_approval',
        'criteria',
        'multipliers',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'requires_approval' => 'boolean',
        'criteria' => 'array',
        'multipliers' => 'array',
    ];

    /**
     * Calculation types
     */
    const CALCULATION_FIXED = 'fixed';
    const CALCULATION_PERCENTAGE = 'percentage';
    const CALCULATION_POINTS_BASED = 'points_based';

    /**
     * Get all employee rewards of this type
     */
    public function employeeRewards(): HasMany
    {
        return $this->hasMany(EmployeeReward::class);
    }

    /**
     * Calculate reward amount based on type and parameters
     */
    public function calculateAmount(array $params = []): float
    {
        $baseSalary = $params['base_salary'] ?? 0;
        $points = $params['points'] ?? 0;
        $performanceScore = $params['performance_score'] ?? 1;

        $baseAmount = match ($this->calculation_type) {
            self::CALCULATION_FIXED => $this->base_amount,
            self::CALCULATION_PERCENTAGE => ($baseSalary * $this->base_amount) / 100,
            self::CALCULATION_POINTS_BASED => $points * $this->base_amount,
            default => 0,
        };

        // Apply performance multiplier if applicable
        if ($this->multipliers && isset($this->multipliers[$performanceScore])) {
            $baseAmount *= $this->multipliers[$performanceScore];
        }

        return $baseAmount;
    }

    /**
     * Scope for active reward types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
