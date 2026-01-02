<?php

namespace App\Http\Resources\V1\Admin;

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
        return [
            'users' => UserResource::collection($this->collection),
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
