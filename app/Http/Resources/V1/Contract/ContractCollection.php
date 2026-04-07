<?php

namespace App\Http\Resources\V1\Contract;

use App\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ContractCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, Contract>|iterable<int, Contract> $resource */
        $resource = $this->resource;

        $pagination = [
            'total' => $this->collection->count(),
            'count' => $this->collection->count(),
            'perPage' => $this->collection->count(),
            'currentPage' => 1,
            'totalPages' => 1,
        ];

        if ($resource instanceof LengthAwarePaginator) {
            $pagination = [
                'total' => $resource->total(),
                'count' => $this->collection->count(),
                'perPage' => $resource->perPage(),
                'currentPage' => $resource->currentPage(),
                'totalPages' => $resource->lastPage(),
            ];
        }

        return [
            'contracts' => ContractResource::collection($this->collection),
            'pagination' => $pagination,
        ];
    }
}
