<?php

namespace App\Services\Sales;

use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadMeeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LeadService
{
    /**
     * Get paginated leads with filters
     */
    public function getLeads(array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(Lead::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('source'),
                AllowedFilter::exact('priority'),
                AllowedFilter::exact('reference_type'),
                AllowedFilter::exact('reference_id'),
                AllowedFilter::exact('assigned_to'),
                'assignedTo' => AllowedFilter::callback('assignedTo', function ($query, $value) {
                    $query->where('assigned_to', $value);
                }),
                AllowedFilter::scope('needs_follow_up'),
                AllowedFilter::scope('active'),
                'search' => AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%")
                            ->orWhere('company', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'priority', 'expected_close_date', 'expected_budget'])
            ->allowedIncludes(['activities', 'meetings', 'quotations', 'timeEntries'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create a new lead
     */
    public function createLead(array $data, User $user): Lead
    {
        // Check for duplicate email or phone
        $this->checkDuplicates($data);
        
        $lead = Lead::create([
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

        return $lead;
    }

    /**
     * Check for duplicate email or phone
     */
    private function checkDuplicates(array $data, ?int $excludeId = null): void
    {
        $query = Lead::query();
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        // Check email
        if (!empty($data['email'])) {
            $existingByEmail = (clone $query)->where('email', $data['email'])->first();
            if ($existingByEmail) {
                throw new \InvalidArgumentException(
                    __('leads.duplicate_email', ['name' => $existingByEmail->name])
                );
            }
        }
        
        // Check phone
        if (!empty($data['phone'])) {
            $existingByPhone = (clone $query)->where('phone', $data['phone'])->first();
            if ($existingByPhone) {
                throw new \InvalidArgumentException(
                    __('leads.duplicate_phone', ['name' => $existingByPhone->name])
                );
            }
        }
    }

    /**
     * Get timezone offset from country code
     */
    private function getTimezoneOffset(?string $country): ?string
    {
        if (!$country) {
            return null;
        }
        
        $timezones = [
            'AE' => '+4',
            'SA' => '+3',
            'EG' => '+2',
            'US' => '-5',
            'GB' => '+0',
            'DE' => '+1',
            'FR' => '+1',
            'IN' => '+5:30',
            'CN' => '+8',
            'JP' => '+9',
            'AU' => '+10',
        ];
        
        return $timezones[strtoupper($country)] ?? null;
    }

    /**
     * Update a lead
     */
    public function updateLead(Lead $lead, array $data, User $user): Lead
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

        // Log status change
        if (isset($data['status']) && $oldStatus !== $lead->status) {
            $this->logStatusChange($lead, $oldStatus, $lead->status, $user);
        }

        return $lead->fresh();
    }

    /**
     * Update lead status
     */
    public function updateStatus(Lead $lead, string $status, User $user, ?string $lossReason = null): Lead
    {
        $oldStatus = $lead->status;

        $updateData = [
            'status' => $status,
            'updated_by' => $user->id,
        ];

        if ($status === LeadStatus::LOST->value && $lossReason) {
            $updateData['loss_reason'] = $lossReason;
        }

        $lead->update($updateData);

        $this->logStatusChange($lead, $oldStatus, $lead->status, $user);

        return $lead->fresh();
    }

    /**
     * Log status change as activity
     */
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

    /**
     * Add activity to lead
     */
    public function addActivity(Lead $lead, array $data, User $user): LeadActivity
    {
        $activity = LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'scheduled_at' => $data['scheduledAt'] ?? null,
            'completed_at' => $data['completedAt'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Update last contact
        if ($data['completedAt'] ?? false) {
            $lead->update(['last_contact_at' => $data['completedAt']]);
        }

        return $activity;
    }

    /**
     * Schedule a meeting
     */
    public function scheduleMeeting(Lead $lead, array $data, User $user): LeadMeeting
    {
        return LeadMeeting::create([
            'lead_id' => $lead->id,
            'title' => $data['title'],
            'location' => $data['location'],
            'meeting_link' => $data['meetingLink'] ?? null,
            'scheduled_at' => $data['scheduledAt'],
            'duration_minutes' => $data['durationMinutes'] ?? 60,
            'attendees' => $data['attendees'] ?? [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Update meeting
     */
    public function updateMeeting(LeadMeeting $meeting, array $data, User $user): LeadMeeting
    {
        $meeting->update([
            'title' => $data['title'] ?? $meeting->title,
            'location' => $data['location'] ?? $meeting->location,
            'meeting_link' => $data['meetingLink'] ?? $meeting->meeting_link,
            'scheduled_at' => $data['scheduledAt'] ?? $meeting->scheduled_at,
            'duration_minutes' => $data['durationMinutes'] ?? $meeting->duration_minutes,
            'attendees' => $data['attendees'] ?? $meeting->attendees,
            'minutes' => $data['minutes'] ?? $meeting->minutes,
            'outcome' => $data['outcome'] ?? $meeting->outcome,
            'updated_by' => $user->id,
        ]);

        return $meeting->fresh();
    }

    /**
     * Get leads by status for Kanban
     */
    public function getLeadsByStatus(): array
    {
        $leads = Lead::with(['activities' => fn($q) => $q->latest()->limit(1)])
            ->active()
            ->get()
            ->groupBy('status');

        $result = [];
        foreach (LeadStatus::cases() as $status) {
            if (!in_array($status, [LeadStatus::WON, LeadStatus::LOST])) {
                $result[$status->value] = $leads->get($status->value, collect())->values();
            }
        }

        return $result;
    }

    /**
     * Get leads needing follow-up
     */
    public function getLeadsNeedingFollowUp(): LengthAwarePaginator
    {
        return Lead::needsFollowUp()
            ->orderBy('next_follow_up_at')
            ->paginate(10);
    }
}
