<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['client', 'project']);

        return [
            'id' => $this->id,
            'invoiceNumber' => $this->invoice_number,
            'clientId' => $this->client_id,
            'projectId' => $this->project_id,
            'invoiceType' => $this->invoice_type ?? 'one_time',
            'billingCycle' => $this->billing_cycle,
            'subscriptionId' => $this->subscription_id,
            'issueDate' => $this->issue_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'taxRate' => (float) $this->tax_rate,
            'taxAmount' => (float) $this->tax_amount,
            'discountAmount' => (float) $this->discount_amount,
            'totalAmount' => (float) $this->total_amount,
            'status' => $this->status,
            'notes' => $this->notes,

            'client' => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->display_name ?? $this->client->company_name ?? '',
                'displayName' => $this->client->display_name ?? $this->client->company_name ?? '',
                'companyName' => $this->client->company_name,
                'primaryEmail' => $this->client->primary_email,
                'primaryPhone' => $this->client->primary_phone,
                'status' => $this->client->status?->value ?? $this->client->status,
            ] : null,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'status' => $this->project->status?->value ?? $this->project->status,
                'budget' => $this->project->budget !== null ? (float) $this->project->budget : null,
                'deadline' => $this->project->deadline?->toDateString(),
            ] : null,
            'items' => $this->whenLoaded(
                'items',
                fn () => FinanceInvoiceItemResource::collection($this->items)
            ),

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
