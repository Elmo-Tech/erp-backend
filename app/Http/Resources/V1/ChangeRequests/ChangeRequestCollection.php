<?php

namespace App\Http\Resources\V1\ChangeRequests;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ChangeRequestCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'changeRequests' => ChangeRequestResource::collection($this->collection),
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
