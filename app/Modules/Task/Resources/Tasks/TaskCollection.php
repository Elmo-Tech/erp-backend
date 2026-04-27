<?php

namespace App\Modules\Task\Resources\Tasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TaskCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * Follows existing collection pattern with pagination support.
     *
     * Requirements: 8.4
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tasks' => TaskResource::collection($this->collection),
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
