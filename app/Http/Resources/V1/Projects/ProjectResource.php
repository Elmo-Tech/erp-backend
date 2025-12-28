<?php

namespace App\Http\Resources\V1\Projects;

use App\Http\Resources\V1\Quotations\QuotationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'projectId' => $this->id,
            'clientId' => $this->client_id ?? 0,
            'quotationId' => $this->quotation_id ?? 0,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'status' => $this->status?->value ?? '',
            'previousStatus' => $this->previous_status?->value ?? '',
            'budget' => (float) ($this->budget ?? 0.0),
            'deadline' => $this->deadline?->toDateString() ?? '',
            'uiApprovedAt' => $this->ui_approved_at?->toISOString() ?? '',
            'autoApprovalJobId' => $this->auto_approval_job_id ?? '',
            'client' => $this->whenLoaded('client', function () {
                return [
                    'userId' => $this->client->id,
                    'name' => $this->client->name ?? '',
                    'email' => $this->client->email ?? '',
                ];
            }),
            'quotation' => $this->whenLoaded('quotation', function () {
                return new QuotationResource($this->quotation);
            }),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
        ];
    }
}
