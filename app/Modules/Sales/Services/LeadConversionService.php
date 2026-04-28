<?php

namespace App\Modules\Sales\Services;

use App\Enums\ClientStatus;
use App\Enums\LeadStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Contract\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class LeadConversionService
{
    /**
     * Convert a lead to a client
     *
     * @param  array  $additionalData  Optional additional client data
     */
    public function convertToClient(Lead $lead, User $convertedBy, array $additionalData = []): Client
    {
        return DB::transaction(function () use ($lead, $convertedBy, $additionalData) {
            // Create client from lead data
            $client = Client::create([
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
            ]);

            // Update lead with conversion info
            $lead->update([
                'client_id' => $client->id,
                'converted_at' => now(),
                'converted_by' => $convertedBy->id,
                'status' => LeadStatus::WON,
            ]);

            return $client;
        });
    }

    /**
     * Convert a lead to client when contract is signed
     * This is called automatically when a contract is signed
     *
     * @param  Lead  $lead  The lead to convert
     * @param  Contract  $contract  The signed contract
     * @param  User  $user  The user performing the conversion
     */
    public function convertLeadToClient(Lead $lead, Contract $contract, User $user): Client
    {
        return DB::transaction(function () use ($lead, $contract, $user) {
            // Check if lead already converted
            if ($lead->client_id) {
                return Client::find($lead->client_id);
            }

            // Create client from lead data
            $client = Client::create([
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company' => $lead->company,
                'country' => $lead->country,
                'status' => ClientStatus::ACTIVE,
                'lead_id' => $lead->id,
            ]);

            // Update the contract with the new client_id
            $contract->update([
                'client_id' => $client->id,
            ]);

            // Update lead with conversion info
            $lead->update([
                'client_id' => $client->id,
                'converted_at' => now(),
                'converted_by' => $user->id,
                'status' => LeadStatus::WON,
            ]);

            return $client;
        });
    }

    /**
     * Create portal access for a client
     */
    public function createPortalAccess(Client $client, string $password): User
    {
        return DB::transaction(function () use ($client, $password) {
            // Create user account for portal access
            $user = User::create([
                'name' => $client->name,
                'email' => $client->email,
                'password' => bcrypt($password),
                'is_active' => true,
                'is_employee' => false,
            ]);

            // Assign client role
            $user->assignRole('client');

            // Link user to client
            $client->update(['user_id' => $user->id]);

            return $user;
        });
    }

    /**
     * Link existing lead to a client (for returning clients)
     */
    public function linkToExistingClient(Lead $lead, Client $client): Lead
    {
        $lead->update([
            'client_id' => $client->id,
        ]);

        return $lead->fresh();
    }

    /**
     * Check if lead can be converted
     *
     * @return array ['canConvert' => bool, 'reason' => string|null]
     */
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

        // Check if email already exists as a client
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
