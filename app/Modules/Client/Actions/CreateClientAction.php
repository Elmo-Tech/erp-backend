<?php

namespace App\Modules\Client\Actions;

use App\Modules\Client\Models\Client;
use Illuminate\Support\Facades\DB;

class CreateClientAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            $client = Client::create($this->normalizeClientData($data));

            $contactData = $data['contact_person'] ?? $data['contactPerson'] ?? null;
            if (is_array($contactData) && ! empty($contactData)) {
                $client->contacts()->create($this->normalizeContactData($client->id, $contactData));
            }

            return $client;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeClientData(array $data): array
    {
        $interests = $data['interests'] ?? null;
        $metadata = $data['metadata'] ?? [];

        if (! is_array($metadata)) {
            $metadata = [];
        }

        if (is_array($interests) && $interests !== []) {
            $metadata['interests'] = array_values($interests);
        }

        $displayName = $this->stringValue($data, ['display_name', 'displayName', 'name', 'company_name', 'companyName', 'company']);
        $companyName = $this->stringValue($data, ['company_name', 'companyName', 'company']);
        $primaryEmail = $this->stringValue($data, ['primary_email', 'primaryEmail', 'email']);
        $primaryPhone = $this->stringValue($data, ['primary_phone', 'primaryPhone', 'phone']);
        $accountManagerId = $data['account_manager_id'] ?? $data['accountManagerId'] ?? null;

        return array_filter([
            'type' => $this->stringValue($data, ['type']) ?: 'company',
            'display_name' => $displayName,
            'legal_name' => $this->stringValue($data, ['legal_name', 'legalName']),
            'company_name' => $companyName,
            'primary_email' => $primaryEmail,
            'primary_phone' => $primaryPhone,
            'secondary_phone' => $this->stringValue($data, ['secondary_phone', 'secondaryPhone']),
            'position' => $this->stringValue($data, ['position']),
            'industry' => $this->stringValue($data, ['industry']),
            'website' => $this->stringValue($data, ['website']),
            'tax_number' => $this->stringValue($data, ['tax_number', 'taxNumber']),
            'address' => $this->stringValue($data, ['address']),
            'city' => $this->stringValue($data, ['city']),
            'state' => $this->stringValue($data, ['state']),
            'country' => $this->stringValue($data, ['country']),
            'postal_code' => $this->stringValue($data, ['postal_code', 'postalCode']),
            'source' => $this->stringValue($data, ['source', 'lead_source', 'leadSource']),
            'lifecycle_stage' => $this->stringValue($data, ['lifecycle_stage', 'lifecycleStage']) ?: 'client',
            'lead_temperature' => $this->stringValue($data, ['lead_temperature', 'leadTemperature']),
            'account_manager_id' => $accountManagerId ?: null,
            'notes' => $this->stringValue($data, ['notes']),
            'metadata' => $metadata,
            'status' => $this->stringValue($data, ['status']) ?: 'active',
            'user_id' => $data['user_id'] ?? $data['userId'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeContactData(int $clientId, array $data): array
    {
        $firstName = $this->stringValue($data, ['first_name', 'firstName']);
        $lastName = $this->stringValue($data, ['last_name', 'lastName']);
        $displayName = $this->stringValue($data, ['display_name', 'displayName']) ?: trim($firstName.' '.$lastName);

        return array_filter([
            'client_id' => $clientId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $displayName,
            'email' => $this->stringValue($data, ['email']),
            'phone' => $this->stringValue($data, ['phone']),
            'mobile' => $this->stringValue($data, ['mobile']),
            'position' => $this->stringValue($data, ['position']),
            'department' => $this->stringValue($data, ['department']),
            'is_primary' => (bool) ($data['is_primary'] ?? $data['isPrimary'] ?? true),
            'notes' => $this->stringValue($data, ['notes']),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    private function stringValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim((string) $data[$key]) !== '') {
                return trim((string) $data[$key]);
            }
        }

        return null;
    }
}
