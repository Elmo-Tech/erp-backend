<?php

namespace App\Modules\Client\Resources;

use App\Modules\Client\Models\Client;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientIndexResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Client $client */
        $client = $this->resource;
        $status = $client->status;

        return [
            'id' => $client->id,
            'type' => $client->type,
            'display_name' => $this->displayName($client),
            'email' => $client->primary_email,
            'company_name' => $client->company_name,
            'position' => $client->position,
            'primary_phone' => $client->primary_phone,
            'status' => $status instanceof BackedEnum ? $status->value : $status,
            'country' => $client->country,
            'projects_count' => (int) ($client->projects_count ?? 0),
            'active_projects_count' => (int) ($client->active_projects_count ?? 0),
            'total_revenue' => round((float) ($client->total_revenue ?? 0), 2),
            'created_at' => $client->created_at?->toISOString(),
        ];
    }

    private function displayName(Client $client): string
    {
        return $client->company_name
            ?: $client->display_name
            ?: $client->legal_name
            ?: $client->primary_email
            ?: 'Unnamed client';
    }
}
