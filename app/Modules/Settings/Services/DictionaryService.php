<?php

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\BaseDictionaryModel;
use Illuminate\Support\Str;

class DictionaryService
{
    /**
     * @var array<string, string>
     */
    private const ENTITY_TABLES = [
        'lead-sources' => 'lead_sources',
        'lead-interests' => 'lead_interests',
        'meeting-types' => 'meeting_types',
        'meeting-statuses' => 'meeting_statuses',
        'proposal-statuses' => 'proposal_statuses',
        'quotation-types' => 'quotation_types',
        'rejection-reasons' => 'rejection_reasons',
        'skip-reasons' => 'skip_reasons',
        'contract-models' => 'contract_models',
        'billing-cycles' => 'billing_cycles',
        'currencies' => 'currencies',
        'discount-types' => 'discount_types',
        'payment-statuses' => 'payment_statuses',
        'project-types' => 'project_types',
        'project-statuses' => 'project_statuses',
        'countries' => 'countries',
        'client-statuses' => 'client_statuses',
        'contact-roles' => 'contact_roles',
    ];

    public function modelFor(string $entity): ?BaseDictionaryModel
    {
        $table = $this->tableFor($entity);

        if ($table === null) {
            return null;
        }

        return (new BaseDictionaryModel)->useTable($table);
    }

    public function tableFor(string $entity): ?string
    {
        return self::ENTITY_TABLES[Str::slug($entity)] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function entityTables(): array
    {
        return self::ENTITY_TABLES;
    }
}
