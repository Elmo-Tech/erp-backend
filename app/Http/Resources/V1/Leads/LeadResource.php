<?php

namespace App\Http\Resources\V1\Leads;

use App\Http\Resources\V1\Activity\ActivityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'leadId' => $this->id,
            'name' => $this->name ?? '',
            'projectName' => $this->project_name ?? '',
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
            'company' => $this->company ?? '',
            'country' => $this->country ?? '',
            'timezoneOffset' => $this->timezone_offset ?? '',
            'industrySector' => $this->industry_sector?->value ?? '',
            'industrySectorLabel' => $this->industry_sector?->label() ?? '',
            'industrySectorIcon' => $this->industry_sector?->icon() ?? '',
            'contactPreferences' => $this->contact_preferences ?? [],
            'source' => $this->source?->value ?? '',
            'sourceLabel' => $this->source?->label() ?? '',
            'referenceType' => $this->reference_type?->value ?? '',
            'referenceId' => $this->reference_id ?? 0,
            'techLeadId' => $this->tech_lead_id ?? 0,
            'techLead' => $this->whenLoaded('techLead', function () {
                return [
                    'userId' => $this->techLead->id,
                    'name' => $this->techLead->name ?? '',
                ];
            }),
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'priority' => $this->priority?->value ?? 'medium',
            'priorityLabel' => $this->priority?->label() ?? '',
            'priorityColor' => $this->priority?->color() ?? 'blue',
            'leadTemperature' => $this->lead_temperature?->value ?? 'warm',
            'leadTemperatureLabel' => $this->lead_temperature?->label() ?? '',
            'leadTemperatureColor' => $this->lead_temperature?->color() ?? 'orange',
            'leadTemperatureIcon' => $this->lead_temperature?->icon() ?? '',
            'expectedBudget' => (float) ($this->expected_budget ?? 0),
            'expectedCloseDate' => $this->expected_close_date?->format('Y-m-d') ?? '',
            'lastContactAt' => $this->last_contact_at?->toISOString() ?? '',
            'nextFollowUpAt' => $this->next_follow_up_at?->toISOString() ?? '',
            'notes' => $this->notes ?? '',
            'lossReason' => $this->loss_reason ?? '',

            // Conversion fields
            'clientId' => $this->client_id ?? 0,
            'convertedAt' => $this->converted_at?->toISOString() ?? '',
            'convertedBy' => $this->converted_by ?? 0,
            'convertedByName' => $this->whenLoaded('convertedBy', fn () => $this->convertedBy?->name ?? ''),
            'isConverted' => $this->isConverted(),
            'client' => $this->whenLoaded('client', function () {
                return [
                    'clientId' => $this->client->id,
                    'name' => $this->client->name ?? '',
                    'company' => $this->client->company ?? '',
                ];
            }),

            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,

            // Relations (when loaded)
            'activities' => LeadActivityResource::collection($this->whenLoaded('activities')),
            'allActivities' => ActivityResource::collection($this->whenLoaded('allActivities')),
            'meetings' => LeadMeetingResource::collection($this->whenLoaded('meetings')),
            'quotationsCount' => $this->whenLoaded('quotations', fn () => $this->quotations->count()),
            'proposalsCount' => $this->whenLoaded('proposals', fn () => $this->proposals->count()),
        ];
    }
}
