<?php

namespace App\Services\Performance;

use App\Enums\BonusTier;
use App\Models\EvaluationPeriod;
use App\Models\PerformanceEvaluation;
use App\Models\User;
use Illuminate\Support\Collection;

class BonusCalculationService
{
    /**
     * Calculate bonus for a single evaluation
     */
    public function calculateBonus(PerformanceEvaluation $evaluation): array
    {
        $user = $evaluation->user;
        $baseAmount = $user->bonus_base_amount ?? 0;
        $score = $evaluation->total_score;

        $tier = BonusTier::fromScore($score);
        $percentage = $tier->percentage();
        $bonusAmount = ($baseAmount * $percentage) / 100;

        // Excellence bonus (extra 10% for 95+ score)
        $excellenceBonus = 0;
        if ($score >= 95) {
            $excellenceBonus = $baseAmount * 0.10;
            $bonusAmount += $excellenceBonus;
        }

        return [
            'userId' => $user->id,
            'userName' => $user->name,
            'totalScore' => $score,
            'bonusTier' => $tier->value,
            'bonusTierLabel' => $tier->label(),
            'baseAmount' => $baseAmount,
            'percentage' => $percentage,
            'excellenceBonus' => $excellenceBonus,
            'finalBonusAmount' => round($bonusAmount, 2),
        ];
    }

    /**
     * Calculate bonuses for all employees in a period
     */
    public function calculatePeriodBonuses(EvaluationPeriod $period): array
    {
        $evaluations = PerformanceEvaluation::where('evaluation_period_id', $period->id)
            ->where('status', 'completed')
            ->with('user')
            ->get();

        $results = [];
        $totalBonuses = 0;

        foreach ($evaluations as $evaluation) {
            $bonus = $this->calculateBonus($evaluation);
            $results[] = $bonus;
            $totalBonuses += $bonus['finalBonusAmount'];

            // Update evaluation with calculated bonus
            $evaluation->update([
                'bonus_tier' => $bonus['bonusTier'],
                'bonus_percentage' => $bonus['percentage'],
                'bonus_amount' => $bonus['finalBonusAmount'],
            ]);
        }

        return [
            'periodId' => $period->id,
            'periodName' => $period->name,
            'evaluations' => $results,
            'summary' => [
                'totalEmployees' => count($results),
                'totalBonusAmount' => round($totalBonuses, 2),
                'byTier' => $this->groupByTier($results),
                'averageScore' => count($results) > 0 
                    ? round(collect($results)->avg('totalScore'), 2) 
                    : 0,
            ],
        ];
    }

    /**
     * Group results by bonus tier
     */
    private function groupByTier(array $results): array
    {
        $grouped = collect($results)->groupBy('bonusTier');

        return [
            'excellent' => [
                'count' => $grouped->get('excellent', collect())->count(),
                'totalBonus' => $grouped->get('excellent', collect())->sum('finalBonusAmount'),
            ],
            'good' => [
                'count' => $grouped->get('good', collect())->count(),
                'totalBonus' => $grouped->get('good', collect())->sum('finalBonusAmount'),
            ],
            'average' => [
                'count' => $grouped->get('average', collect())->count(),
                'totalBonus' => $grouped->get('average', collect())->sum('finalBonusAmount'),
            ],
            'poor' => [
                'count' => $grouped->get('poor', collect())->count(),
                'totalBonus' => 0,
            ],
        ];
    }

    /**
     * Get bonus history for a user
     */
    public function getUserBonusHistory(User $user, int $limit = 6): Collection
    {
        return PerformanceEvaluation::where('user_id', $user->id)
            ->where('status', 'approved')
            ->with('period')
            ->orderByDesc('evaluated_at')
            ->limit($limit)
            ->get()
            ->map(fn($eval) => [
                'periodId' => $eval->evaluation_period_id,
                'periodName' => $eval->period->name,
                'totalScore' => $eval->total_score,
                'bonusTier' => $eval->bonus_tier,
                'bonusAmount' => $eval->bonus_amount,
                'evaluatedAt' => $eval->evaluated_at,
            ]);
    }

    /**
     * Get bonus projection based on current performance
     */
    public function getBonusProjection(User $user): array
    {
        $baseAmount = $user->bonus_base_amount ?? 0;

        return [
            'baseAmount' => $baseAmount,
            'projections' => [
                'excellent' => [
                    'minScore' => 90,
                    'percentage' => 100,
                    'amount' => $baseAmount,
                ],
                'good' => [
                    'minScore' => 80,
                    'percentage' => 75,
                    'amount' => $baseAmount * 0.75,
                ],
                'average' => [
                    'minScore' => 60,
                    'percentage' => 50,
                    'amount' => $baseAmount * 0.50,
                ],
                'poor' => [
                    'minScore' => 0,
                    'percentage' => 0,
                    'amount' => 0,
                ],
            ],
        ];
    }
}
