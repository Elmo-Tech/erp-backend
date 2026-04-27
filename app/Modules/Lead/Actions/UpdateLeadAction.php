<?php

namespace App\Modules\Lead\Actions;

use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadActivity;
use App\Modules\Lead\Models\LeadMeeting;
use App\Modules\User\Models\User;

class UpdateLeadAction
{
    public function execute(Lead $lead, array $data, User $user): Lead
    {
        $oldStatus = $lead->status;

        $lead->update([
            'name' => $data['name'] ?? $lead->name,
            'project_name' => $data['projectName'] ?? $lead->project_name,
            'email' => $data['email'] ?? $lead->email,
            'phone' => $data['phone'] ?? $lead->phone,
            'company' => $data['company'] ?? $lead->company,
            'country' => $data['country'] ?? $lead->country,
            'timezone_offset' => $data['timezoneOffset'] ?? $lead->timezone_offset,
            'industry_sector' => $data['industrySector'] ?? $lead->industry_sector,
            'contact_preferences' => $data['contactPreferences'] ?? $lead->contact_preferences,
            'source' => $data['source'] ?? $lead->source,
            'reference_type' => $data['referenceType'] ?? $lead->reference_type,
            'reference_id' => $data['referenceId'] ?? $lead->reference_id,
            'tech_lead_id' => $data['techLeadId'] ?? $lead->tech_lead_id,
            'status' => $data['status'] ?? $lead->status,
            'priority' => $data['priority'] ?? $lead->priority,
            'lead_temperature' => $data['leadTemperature'] ?? $lead->lead_temperature,
            'expected_budget' => $data['expectedBudget'] ?? $lead->expected_budget,
            'expected_close_date' => $data['expectedCloseDate'] ?? $lead->expected_close_date,
            'next_follow_up_at' => $data['nextFollowUpAt'] ?? $lead->next_follow_up_at,
            'notes' => $data['notes'] ?? $lead->notes,
            'loss_reason' => $data['lossReason'] ?? $lead->loss_reason,
            'updated_by' => $user->id,
        ]);

        if (isset($data['status']) && $oldStatus !== $lead->status) {
            $this->logStatusChange($lead, $oldStatus, $lead->status, $user);
        }

        return $lead->fresh();
    }

    public function updateStatus(
        Lead $lead,
        string $status,
        User $user,
        ?string $lossReason = null,
        ?int $meetingId = null,
        ?string $outcome = null,
        ?string $feedback = null,
        ?int $teamLeadId = null
    ): Lead {
        $oldStatus = $lead->status;

        $updateData = [
            'status' => $status,
            'updated_by' => $user->id,
        ];

        if ($status === LeadStatus::LOST->value && $lossReason) {
            $updateData['loss_reason'] = $lossReason;
        }

        $lead->update($updateData);

        if ($meetingId && $status === LeadStatus::MEETING_COMPLETED->value) {
            $meeting = LeadMeeting::find($meetingId);
            if (! $meeting) {
                \Log::warning("Meeting {$meetingId} not found for lead {$lead->id}");
            } elseif ($meeting->lead_id !== $lead->id) {
                \Log::warning("Meeting {$meetingId} does not belong to lead {$lead->id}");
            } else {
                $meeting->update([
                    'outcome' => $outcome,
                    'feedback' => $feedback,
                    'team_lead_id' => $teamLeadId,
                    'updated_by' => $user->id,
                ]);
            }
        }

        $this->logStatusChange($lead, $oldStatus, $lead->status, $user);

        return $lead->fresh();
    }

    private function logStatusChange(Lead $lead, $oldStatus, $newStatus, User $user): void
    {
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'type' => ActivityType::STATUS_CHANGE,
            'title' => __('leads.status_changed'),
            'description' => __('leads.status_changed_from_to', [
                'from' => $oldStatus->label(),
                'to' => $newStatus->label(),
            ]),
            'completed_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
