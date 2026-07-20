<?php

namespace App\Modules\Client\Resources;

use App\Modules\Client\Models\Client;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientPortalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Client $client */
        $client = $this->resource;
        $status = $client->getAttribute('status');

        return [
            'clientId' => $client->id,
            'displayName' => $client->display_name ?? '',
            'companyName' => $client->company_name ?? '',
            'primaryEmail' => $client->primary_email ?? '',
            'primaryPhone' => $client->primary_phone ?? '',
            'status' => $status instanceof BackedEnum ? $status->value : (is_scalar($status) ? (string) $status : ''),
            'country' => $client->country ?? '',
        ];
    }
}
