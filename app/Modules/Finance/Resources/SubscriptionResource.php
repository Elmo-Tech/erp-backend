<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->client_id,
            'projectId' => $this->project_id,
            'name' => $this->name,
            'provider' => $this->provider,
            'billingCycle' => $this->billing_cycle,
            'cost' => (float) $this->cost,
            'currency' => $this->currency,
            'nextBillingDate' => $this->next_billing_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
