<?php

namespace App\Http\Resources\V1\Client;

use App\Services\Client\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * CRITICAL: This resource hides ALL internal data from clients.
     * 
     * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progressService = app(ProgressService::class);

        return [
            'projectId' => $this->id ?? 0,
            'title' => $this->title ?? '',
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'progressPercentage' => $progressService->calculateProgress($this->resource),
            'deadline' => $this->deadline?->toDateString() ?? '',
            'budget' => (float) ($this->budget ?? 0.0),
            'currency' => 'USD', // Default currency as per requirements
            'uiApprovedAt' => $this->ui_approved_at?->toISOString() ?? '',
            'canApproveUI' => $this->status?->value === 'ui_ux' && !$this->ui_approved_at,
            'createdAt' => $this->created_at?->toISOString() ?? '',
            // Invoices for this project (using ClientInvoiceResource)
            'invoices' => $this->whenLoaded('invoices', function () {
                return ClientInvoiceResource::collection($this->invoices);
            }),
            // HIDDEN: description, tasks, developers, wikis, timeEntries, internalNotes
        ];
    }
}