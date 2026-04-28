<?php

namespace App\Modules\HR\Resources;

use App\Modules\HR\Models\RewardType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RewardTypeCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, RewardType>|iterable<int, RewardType> $resource */
        $resource = $this->resource;

        $meta = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $this->collection->count(),
            'total' => $this->collection->count(),
        ];

        if ($resource instanceof LengthAwarePaginator) {
            $meta = [
                'current_page' => $resource->currentPage(),
                'last_page' => $resource->lastPage(),
                'per_page' => $resource->perPage(),
                'total' => $resource->total(),
            ];
        }

        return [
            'data' => RewardTypeResource::collection($this->collection),
            'meta' => $meta,
        ];
    }
}
