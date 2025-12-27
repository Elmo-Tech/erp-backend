<?php

namespace App\Http\Resources\V1\Leads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'leadId' => $this->id,
            'name' => $this->name ?? '',
            'email' => $this->email ?? '',
            'company' => $this->company ?? '',
            'source' => $this->source ?? '',
            'status' => $this->status?->value ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
        ];
    }
}