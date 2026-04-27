<?php

namespace App\Modules\Employee\Resources;

use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, User>|iterable<int, User> $resource */
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
            'employees' => EmployeeResource::collection($this->collection),
            'pagination' => $pagination,
        ];
    }
}
