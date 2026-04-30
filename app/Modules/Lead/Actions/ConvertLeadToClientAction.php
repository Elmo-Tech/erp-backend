<?php

namespace App\Modules\Lead\Actions;

use App\Enums\ClientStatus;
use App\Enums\LeadStatus;
use App\Modules\Client\Actions\CreateClientAction;
use App\Modules\CRM\Models\Client;
use App\Modules\Lead\Models\Lead;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class ConvertLeadToClientAction
{
    public function __construct(
        private CreateClientAction $createClientAction
    ) {}

    public function execute(Lead $lead, User $convertedBy, array $additionalData = []): Client
    {
        return DB::transaction(function () use ($lead, $convertedBy, $additionalData) {
            $clientData = [
                'name' => $additionalData['name'] ?? $lead->name,
                'email' => $additionalData['email'] ?? $lead->email,
                'phone' => $additionalData['phone'] ?? $lead->phone,
                'company' => $additionalData['company'] ?? $lead->company,
                'country' => $additionalData['country'] ?? $lead->country,
                'position' => $additionalData['position'] ?? null,
                'address' => $additionalData['address'] ?? null,
                'city' => $additionalData['city'] ?? null,
                'website' => $additionalData['website'] ?? null,
                'notes' => $additionalData['notes'] ?? $lead->notes,
                'status' => ClientStatus::ACTIVE,
                'lead_id' => $lead->id,
            ];

            $client = $this->createClientAction->execute($clientData);

            $lead->update([
                'client_id' => $client->id,
                'converted_at' => now(),
                'converted_by' => $convertedBy->id,
                'status' => LeadStatus::WON,
            ]);

            return $client;
        });
    }

    public function executeFromContract(Lead $lead, \App\Modules\Contract\Models\Contract $contract, User $user): Client
    {
        return DB::transaction(function () use ($lead, $contract, $user) {
            if ($lead->client_id) {
                return Client::find($lead->client_id);
            }

            $clientData = [
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company' => $lead->company,
                'country' => $lead->country,
                'status' => ClientStatus::ACTIVE,
                'lead_id' => $lead->id,
            ];

            $client = $this->createClientAction->execute($clientData);

            $contract->update([
                'client_id' => $client->id,
            ]);

            $lead->update([
                'client_id' => $client->id,
                'converted_at' => now(),
                'converted_by' => $user->id,
                'status' => LeadStatus::WON,
            ]);

            return $client;
        });
    }

    public function linkToExistingClient(Lead $lead, Client $client): Lead
    {
        $lead->update([
            'client_id' => $client->id,
        ]);

        return $lead->fresh();
    }

    public function canConvert(Lead $lead): array
    {
        if ($lead->isConverted()) {
            return [
                'canConvert' => false,
                'reason' => __('Lead has already been converted to a client'),
            ];
        }

        if ($lead->status === LeadStatus::LOST) {
            return [
                'canConvert' => false,
                'reason' => __('Cannot convert a lost lead'),
            ];
        }

        if (empty($lead->email)) {
            return [
                'canConvert' => false,
                'reason' => __('Lead must have an email address to be converted'),
            ];
        }

        $existingClient = Client::where('email', $lead->email)->first();
        if ($existingClient) {
            return [
                'canConvert' => true,
                'reason' => __('A client with this email already exists. Lead will be linked to existing client.'),
                'existingClient' => $existingClient,
            ];
        }

        return [
            'canConvert' => true,
            'reason' => null,
        ];
    }
}
