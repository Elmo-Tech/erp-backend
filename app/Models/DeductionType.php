<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string $calculation_type
 * @property float|int|string $amount
 * @property bool $is_active
 * @property bool $requires_approval
 * @property int $grace_period_minutes
 * @property array<string, mixed>|null $rules
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeductionRule> $deductionRules
 */
class DeductionType extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'calculation_type',
        'amount',
        'is_active',
        'requires_approval',
        'grace_period_minutes',
        'rules',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'requires_approval' => 'boolean',
        'grace_period_minutes' => 'integer',
        'rules' => 'array',
    ];

    /**
     * Calculation types
     */
    const CALCULATION_FIXED = 'fixed';

    const CALCULATION_PER_OCCURRENCE = 'per_occurrence';

    const CALCULATION_PERCENTAGE = 'percentage';

    const CALCULATION_PER_MINUTE = 'per_minute';

    /**
     * Get all employee deductions of this type
     */
    public function employeeDeductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    /**
     * Get deduction rules for this type
     */
    public function deductionRules(): HasMany
    {
        return $this->hasMany(DeductionRule::class);
    }

    /**
     * Backward-compatible alias used by legacy controllers/resources.
     */
    public function rules(): HasMany
    {
        return $this->deductionRules();
    }

    /**
     * Calculate deduction amount based on type and parameters
     */
    public function calculateAmount(array $params = []): float
    {
        $lateMinutes = $params['late_minutes'] ?? 0;
        $baseSalary = $params['base_salary'] ?? 0;

        return match ($this->calculation_type) {
            self::CALCULATION_FIXED => $this->amount,
            self::CALCULATION_PER_OCCURRENCE => $this->amount,
            self::CALCULATION_PERCENTAGE => ($baseSalary * $this->amount) / 100,
            self::CALCULATION_PER_MINUTE => $lateMinutes * $this->amount,
            default => 0,
        };
    }

    /**
     * Scope for active deduction types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
