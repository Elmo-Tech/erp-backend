<?php

namespace App\Http\Resources\V1\Performance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PerformanceEvaluationCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'evaluations' => PerformanceEvaluationResource::collection($this->collection),
            'pagination' => [
                'total' => $this->total(),
                'count' => $this->count(),
                'perPage' => $this->perPage(),
                'currentPage' => $this->currentPage(),
                'totalPages' => $this->lastPage(),
            ],
        ];
    }
}
