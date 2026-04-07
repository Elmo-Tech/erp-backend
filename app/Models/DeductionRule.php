<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $deduction_type_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property string $rule_type
 * @property float|int|string $threshold_value
 * @property string|null $threshold_unit
 * @property float|int|string $deduction_value
 * @property string|null $deduction_unit
 * @property float|int|string $max_deduction_amount
 * @property float|int|string $repetition_penalty_factor
 * @property int $grace_period_days
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_until
 * @property array<string, mixed>|null $conditions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DeductionRule extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
        'deduction_type_id',
        'name',
        'code',
        'description',
        'rule_type',
        'threshold_value',
        'threshold_unit',
        'deduction_value',
        'deduction_unit',
        'max_deduction_amount',
        'repetition_penalty_factor',
        'grace_period_days',
        'is_active',
        'effective_from',
        'effective_until',
        'conditions',
    ];

    protected $casts = [
        'threshold_value' => 'decimal:2',
        'deduction_value' => 'decimal:2',
        'max_deduction_amount' => 'decimal:2',
        'repetition_penalty_factor' => 'decimal:2',
        'grace_period_days' => 'integer',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'conditions' => 'array',
    ];

    /**
     * Rule types
     */
    const RULE_TYPE_FIXED = 'fixed';

    const RULE_TYPE_TIERED = 'tiered';

    const RULE_TYPE_ACCUMULATIVE = 'accumulative';

    const RULE_TYPE_PERCENTAGE = 'percentage';

    /**
     * Deduction units
     */
    const UNIT_AMOUNT = 'amount';

    const UNIT_PERCENTAGE = 'percentage';

    const UNIT_DAYS_SALARY = 'days_salary';

    /**
     * Get the deduction type this rule belongs to
     */
    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
    }

    /**
     * Calculate deduction amount based on occurrences and salary
     */
    public function calculateDeduction(int $occurrences, float $baseSalary, array $context = []): float
    {
        if (! $this->is_active) {
            return 0;
        }

        // Check if within grace period
        if (isset($context['is_within_grace_period']) && $context['is_within_grace_period']) {
            return 0;
        }

        $deduction = 0;

        switch ($this->rule_type) {
            case self::RULE_TYPE_FIXED:
                $deduction = $this->calculateFixedDeduction($occurrences, $baseSalary);
                break;
            case self::RULE_TYPE_TIERED:
                $deduction = $this->calculateTieredDeduction($occurrences, $baseSalary);
                break;
            case self::RULE_TYPE_ACCUMULATIVE:
                $deduction = $this->calculateAccumulativeDeduction($occurrences, $baseSalary);
                break;
            case self::RULE_TYPE_PERCENTAGE:
                $deduction = $this->calculatePercentageDeduction($occurrences, $baseSalary);
                break;
        }

        // Apply maximum cap if set
        if ($this->max_deduction_amount > 0) {
            $deduction = min($deduction, $this->max_deduction_amount);
        }

        return round($deduction, 2);
    }

    /**
     * Calculate fixed amount deduction
     */
    protected function calculateFixedDeduction(int $occurrences, float $baseSalary): float
    {
        if ($occurrences < $this->threshold_value) {
            return 0;
        }

        $amount = $this->deduction_value;

        // Apply repetition penalty for multiple occurrences
        if ($occurrences > 1 && $this->repetition_penalty_factor > 0) {
            $penaltyOccurrences = $occurrences - 1;
            $amount += ($this->deduction_value * $this->repetition_penalty_factor * $penaltyOccurrences);
        }

        return $this->convertToAmount($amount, $baseSalary);
    }

    /**
     * Calculate tiered deduction (increases with occurrences)
     */
    protected function calculateTieredDeduction(int $occurrences, float $baseSalary): float
    {
        $totalDeduction = 0;

        for ($i = 1; $i <= $occurrences; $i++) {
            $tierDeduction = $this->deduction_value * $i;
            $totalDeduction += $this->convertToAmount($tierDeduction, $baseSalary);
        }

        return $totalDeduction;
    }

    /**
     * Calculate accumulative deduction
     */
    protected function calculateAccumulativeDeduction(int $occurrences, float $baseSalary): float
    {
        $excessOccurrences = max(0, $occurrences - $this->threshold_value);

        return $this->convertToAmount($this->deduction_value * $excessOccurrences, $baseSalary);
    }

    /**
     * Calculate percentage-based deduction
     */
    protected function calculatePercentageDeduction(int $occurrences, float $baseSalary): float
    {
        if ($occurrences < $this->threshold_value) {
            return 0;
        }

        $percentage = $this->deduction_value * $occurrences;

        return ($baseSalary * $percentage) / 100;
    }

    /**
     * Convert deduction value to actual amount based on unit
     */
    protected function convertToAmount(float $value, float $baseSalary): float
    {
        return match ($this->deduction_unit) {
            self::UNIT_AMOUNT => $value,
            self::UNIT_PERCENTAGE => ($baseSalary * $value) / 100,
            self::UNIT_DAYS_SALARY => ($baseSalary / 30) * $value,
            default => $value,
        };
    }

    /**
     * Scope for active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', now());
            });
    }

    /**
     * Check if rule is currently effective
     */
    public function isEffective(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->effective_from && $now->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_until && $now->gt($this->effective_until)) {
            return false;
        }

        return true;
    }
}
