<?php

namespace App\Http\Resources\V1\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class LeadHistoryCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'histories' => LeadHistoryResource::collection($this->collection),
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
