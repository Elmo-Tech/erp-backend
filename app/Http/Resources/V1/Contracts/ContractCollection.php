<?php

namespace App\Http\Resources\V1\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ContractCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'contracts' => ContractResource::collection($this->collection),
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
