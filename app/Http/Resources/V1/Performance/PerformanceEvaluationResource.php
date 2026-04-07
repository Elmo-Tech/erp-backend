<?php

namespace App\Http\Resources\V1\Performance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'evaluationId' => $this->id,
            'userId' => $this->user_id,
            'userName' => $this->whenLoaded('user', fn () => $this->user->name, ''),
            'periodId' => $this->evaluation_period_id,
            'periodName' => $this->whenLoaded('period', fn () => $this->period->name, ''),
            'scores' => [
                'technicalQuality' => [
                    'bugSeverity' => (float) ($this->bug_severity_score ?? 0),
                    'codeQuality' => (float) ($this->code_quality_score ?? 0),
                    'total' => (float) ($this->technical_quality_total ?? 0),
                ],
                'innovation' => (float) ($this->innovation_score ?? 0),
                'productivity' => [
                    'onTimeDelivery' => (float) ($this->on_time_delivery_score ?? 0),
                    'flexibility' => (float) ($this->flexibility_score ?? 0),
                    'total' => (float) ($this->productivity_total ?? 0),
                ],
                'softSkills' => [
                    'communication' => (float) ($this->communication_score ?? 0),
                    'attendance' => (float) ($this->attendance_score ?? 0),
                    'initiative' => (float) ($this->initiative_score ?? 0),
                    'total' => (float) ($this->soft_skills_total ?? 0),
                ],
            ],
            'totalScore' => (float) ($this->total_score ?? 0),
            'bonusTier' => $this->bonus_tier ?? '',
            'bonusPercentage' => (float) ($this->bonus_percentage ?? 0),
            'bonusAmount' => (float) ($this->bonus_amount ?? 0),
            'evaluatorNotes' => $this->evaluator_notes ?? '',
            'evaluatedBy' => $this->evaluated_by ?? 0,
            'evaluatorName' => $this->whenLoaded('evaluator', fn () => $this->evaluator?->name ?? '', ''),
            'evaluatedAt' => $this->evaluated_at?->toISOString() ?? '',
            'status' => $this->status ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}
