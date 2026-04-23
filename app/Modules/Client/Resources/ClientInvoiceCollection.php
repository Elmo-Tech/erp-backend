<?php

namespace App\Modules\Client\Resources;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClientInvoiceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * Requirements: 8.1
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, Invoice>|iterable<int, Invoice> $resource */
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
            'invoices' => ClientInvoiceResource::collection($this->collection),
            'pagination' => $pagination,
        ];
    }
}
