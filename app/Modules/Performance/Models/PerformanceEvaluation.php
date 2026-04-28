<?php

namespace App\Modules\Performance\Models;

use App\Modules\User\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'evaluation_period_id',
        // Technical Quality
        'bug_severity_score',
        'code_quality_score',
        'technical_quality_total',
        // Innovation
        'innovation_score',
        // Productivity
        'on_time_delivery_score',
        'flexibility_score',
        'productivity_total',
        // Soft Skills
        'communication_score',
        'attendance_score',
        'initiative_score',
        'soft_skills_total',
        // Totals
        'total_score',
        'bonus_tier',
        'bonus_percentage',
        'bonus_amount',
        // Metadata
        'evaluator_notes',
        'evaluated_by',
        'evaluated_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'bug_severity_score' => 'decimal:2',
            'code_quality_score' => 'decimal:2',
            'technical_quality_total' => 'decimal:2',
            'innovation_score' => 'decimal:2',
            'on_time_delivery_score' => 'decimal:2',
            'flexibility_score' => 'decimal:2',
            'productivity_total' => 'decimal:2',
            'communication_score' => 'decimal:2',
            'attendance_score' => 'decimal:2',
            'initiative_score' => 'decimal:2',
            'soft_skills_total' => 'decimal:2',
            'total_score' => 'decimal:2',
            'bonus_percentage' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'evaluated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriod::class, 'evaluation_period_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function calculateTotals(): void
    {
        $this->technical_quality_total = $this->bug_severity_score + $this->code_quality_score;
        $this->productivity_total = $this->on_time_delivery_score + $this->flexibility_score;
        $this->soft_skills_total = $this->communication_score + $this->attendance_score + $this->initiative_score;

        $this->total_score = $this->technical_quality_total
            + $this->innovation_score
            + $this->productivity_total
            + $this->soft_skills_total;
    }

    public function determineBonusTier(): void
    {
        $score = $this->total_score;

        if ($score >= 90) {
            $this->bonus_tier = 'excellent';
            $this->bonus_percentage = 100;
        } elseif ($score >= 80) {
            $this->bonus_tier = 'good';
            $this->bonus_percentage = 75;
        } elseif ($score >= 60) {
            $this->bonus_tier = 'average';
            $this->bonus_percentage = 50;
        } else {
            $this->bonus_tier = 'poor';
            $this->bonus_percentage = 0;
        }
    }

    public function calculateBonusAmount(): void
    {
        $baseAmount = $this->user->bonus_base_amount ?? 0;
        $this->bonus_amount = ($baseAmount * $this->bonus_percentage) / 100;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
