<?php

namespace App\Http\Resources\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClientProjectCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     * 
     * Requirements: 9.1
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'projects' => ClientProjectResource::collection($this->collection),
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