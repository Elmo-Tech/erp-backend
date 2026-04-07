<?php

namespace App\Http\Resources\V1\Performance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'periodId' => $this->id,
            'name' => $this->name ?? '',
            'startDate' => $this->start_date?->format('Y-m-d') ?? '',
            'endDate' => $this->end_date?->format('Y-m-d') ?? '',
            'status' => $this->status ?? '',
            'evaluationsCount' => $this->whenLoaded('evaluations', fn () => $this->evaluations->count(), 0),
            'createdBy' => $this->created_by ?? 0,
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }
}
