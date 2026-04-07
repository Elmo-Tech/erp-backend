<?php

namespace App\Http\Resources\V1\ChangeRequests;

use App\Models\ChangeRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ChangeRequestCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, ChangeRequest>|iterable<int, ChangeRequest> $resource */
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
            'changeRequests' => ChangeRequestResource::collection($this->collection),
            'pagination' => $pagination,
        ];
    }
}
