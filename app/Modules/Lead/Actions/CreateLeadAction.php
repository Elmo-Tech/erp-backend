<?php

namespace App\Modules\Lead\Actions;

use App\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\User\Models\User;

class CreateLeadAction
{
    public function execute(array $data, User $user): Lead
    {
        $this->checkDuplicates($data);

        return Lead::create([
            'name' => $data['name'],
            'project_name' => $data['projectName'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'country' => $data['country'] ?? null,
            'timezone_offset' => $data['timezoneOffset'] ?? $this->getTimezoneOffset($data['country'] ?? null),
            'industry_sector' => $data['industrySector'] ?? null,
            'contact_preferences' => $data['contactPreferences'] ?? null,
            'source' => $data['source'] ?? null,
            'reference_type' => $data['referenceType'] ?? null,
            'reference_id' => $data['referenceId'] ?? null,
            'tech_lead_id' => $data['techLeadId'] ?? null,
            'status' => $data['status'] ?? LeadStatus::NEW,
            'priority' => $data['priority'] ?? 'medium',
            'lead_temperature' => $data['leadTemperature'] ?? 'warm',
            'expected_budget' => $data['expectedBudget'] ?? null,
            'expected_close_date' => $data['expectedCloseDate'] ?? null,
            'next_follow_up_at' => $data['nextFollowUpAt'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function checkDuplicates(array $data, ?int $excludeId = null): void
    {
        $query = Lead::query();

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if (! empty($data['email'])) {
            $existingByEmail = (clone $query)->where('email', $data['email'])->first();
            if ($existingByEmail) {
                throw new \InvalidArgumentException(
                    __('leads.duplicate_email', ['name' => $existingByEmail->name])
                );
            }
        }

        if (! empty($data['phone'])) {
            $existingByPhone = (clone $query)->where('phone', $data['phone'])->first();
            if ($existingByPhone) {
                throw new \InvalidArgumentException(
                    __('leads.duplicate_phone', ['name' => $existingByPhone->name])
                );
            }
        }
    }

    private function getTimezoneOffset(?string $country): ?string
    {
        if (! $country) {
            return null;
        }

        $timezones = [
            'AE' => '+4', 'SA' => '+3', 'EG' => '+2', 'US' => '-5',
            'GB' => '+0', 'DE' => '+1', 'FR' => '+1', 'IN' => '+5:30',
            'CN' => '+8', 'JP' => '+9', 'AU' => '+10',
        ];

        return $timezones[strtoupper($country)] ?? null;
    }
}
