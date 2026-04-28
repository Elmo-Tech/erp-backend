<?php

namespace App\Modules\ProjectHandover\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CodeReviewCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'codeReviews' => CodeReviewResource::collection($this->collection),
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
