<?php

namespace App\Modules\User\Resources;

use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UserCollection',
    description: 'Paginated collection of users',
    properties: [
        new OA\Property(
            property: 'users',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/UserResource')
        ),
        new OA\Property(
            property: 'pagination',
            type: 'object',
            properties: [
                new OA\Property(property: 'total', type: 'integer', example: 50),
                new OA\Property(property: 'count', type: 'integer', example: 15),
                new OA\Property(property: 'perPage', type: 'integer', example: 15),
                new OA\Property(property: 'currentPage', type: 'integer', example: 1),
                new OA\Property(property: 'totalPages', type: 'integer', example: 4),
            ]
        ),
    ]
)]
class UserCollection extends ResourceCollection
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
            'users' => UserResource::collection($this->collection),
            'pagination' => $pagination,
        ];
    }
}
