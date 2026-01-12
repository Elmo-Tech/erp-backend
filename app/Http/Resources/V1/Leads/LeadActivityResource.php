<?php

namespace App\Http\Resources\V1\Leads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'activityId' => $this->id,
            'leadId' => $this->lead_id,
            'userId' => $this->user_id,
            'userName' => $this->whenLoaded('user', fn() => $this->user->name ?? ''),
            'type' => $this->type?->value ?? '',
            'typeLabel' => $this->type?->label() ?? '',
            'typeIcon' => $this->type?->icon() ?? '',
            'status' => $this->status?->value ?? 'scheduled',
            'statusLabel' => $this->status?->label() ?? '',
            'statusColor' => $this->status?->color() ?? 'blue',
            'statusIcon' => $this->status?->icon() ?? '',
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'outcome' => $this->outcome ?? '',
            'cancellationReason' => $this->cancellation_reason ?? '',
            'nextAction' => $this->next_action ?? '',
            'reminderAt' => $this->reminder_at?->toISOString() ?? '',
            'reminderSent' => (bool) $this->reminder_sent,
            'outcomeReminderSent' => (bool) $this->outcome_reminder_sent,
            'scheduledAt' => $this->scheduled_at?->toISOString() ?? '',
            'completedAt' => $this->completed_at?->toISOString() ?? '',
            'isScheduled' => $this->isScheduled(),
            'isCompleted' => $this->isCompleted(),
            'needsOutcome' => $this->needsOutcome(),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}
