<?php

namespace App\Modules\Lead\Actions;

use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadActivity;
use App\Modules\User\Models\User;

class AddLeadActivityAction
{
    public function execute(Lead $lead, array $data, User $user): LeadActivity
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

        if ($data['completedAt'] ?? false) {
            $lead->update(['last_contact_at' => $data['completedAt']]);
        }

        return $activity;
    }
}
