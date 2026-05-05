<?php

namespace App\Modules\Client\Resources;

use App\Modules\Client\Models\Client;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Client $client */
        $client = $this->resource;
        $status = $client->status;
        $hasPortalAccess = (bool) ($client->user_id && $client->user?->is_active);

        return [
            'clientId' => $client->id,
            'name' => $client->display_name ?? $client->legal_name ?? '',
            'email' => $client->primary_email ?? '',
            'phone' => $client->primary_phone ?? '',
            'company' => $client->company_name ?? '',
            'type' => $client->type ?? '',
            'displayName' => $client->display_name ?? '',
            'legalName' => $client->legal_name ?? '',
            'primaryEmail' => $client->primary_email ?? '',
            'primaryPhone' => $client->primary_phone ?? '',
            'secondaryPhone' => $client->secondary_phone ?? '',
            'companyName' => $client->company_name ?? '',
            'position' => $client->position ?? '',
            'industry' => $client->industry ?? '',
            'address' => $client->address ?? '',
            'city' => $client->city ?? '',
            'state' => $client->state ?? '',
            'country' => $client->country ?? '',
            'postalCode' => $client->postal_code ?? '',
            'website' => $client->website ?? '',
            'taxNumber' => $client->tax_number ?? '',
            'source' => $client->source ?? '',
            'lifecycleStage' => $client->lifecycle_stage ?? '',
            'leadTemperature' => $client->lead_temperature ?? '',
            'accountManagerId' => $client->account_manager_id,
            'accountManager' => $this->whenLoaded('accountManager', function () use ($client) {
                if (! $client->accountManager) {
                    return null;
                }

                return [
                    'userId' => $client->accountManager->id,
                    'name' => $client->accountManager->name,
                    'email' => $client->accountManager->email,
                ];
            }),
            'metadata' => $client->metadata ?? [],
            'notes' => $client->notes ?? '',
            'status' => $status instanceof BackedEnum ? $status->value : ($status ?? ''),
            'userId' => $client->user_id,
            'leadId' => $client->lead_id ?? null,
            'hasPortalAccess' => $hasPortalAccess,
            'totalRevenue' => (float) ($client->total_revenue ?? 0),
            'activeProjectsCount' => (int) ($client->active_projects_count ?? 0),
            'contacts' => $this->whenLoaded('contacts', function () use ($client) {
                return $client->contacts->map(fn ($contact) => [
                    'contactId' => $contact->id,
                    'firstName' => $contact->first_name,
                    'lastName' => $contact->last_name,
                    'displayName' => $contact->display_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'mobile' => $contact->mobile,
                    'position' => $contact->position,
                    'department' => $contact->department,
                    'isPrimary' => (bool) $contact->is_primary,
                ])->values()->all();
            }),
            'createdAt' => $client->created_at?->toISOString() ?? '',
            'updatedAt' => $client->updated_at?->toISOString() ?? '',
        ];
    }
}
