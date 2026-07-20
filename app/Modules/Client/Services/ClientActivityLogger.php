<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientActivity;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClientActivityLogger
{
    public function log(Client $client, ?User $user, string $action, string $description): void
    {
        try {
            ClientActivity::create([
                'client_id' => $client->id,
                'user_id' => $user?->id,
                'action' => $action,
                'description' => $description,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Client activity logging failed', [
                'client_id' => $client->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function clientCreated(Client $client, ?User $user): void
    {
        $actorName = $user?->name ?? 'System';

        $this->log(
            $client,
            $user,
            'Client Created',
            "Client '{$client->display_name}' created by {$actorName}."
        );
    }

    public function projectCreated(Project $project, ?User $user): void
    {
        $client = $project->relationLoaded('client') ? $project->client : $project->client()->first();

        if (! $client) {
            return;
        }

        $actorName = $user?->name ?? 'System';

        $this->log(
            $client,
            $user,
            'Project Created',
            "Project '{$project->title}' created by {$actorName}."
        );
    }

    public function meetingScheduled(Meeting $meeting, Client $client, ?User $user): void
    {
        $scheduledAt = $meeting->scheduled_at?->format('M j, Y H:i') ?? 'an upcoming date';

        $this->log(
            $client,
            $user,
            'Meeting Scheduled',
            "{$meeting->title} scheduled for {$scheduledAt}."
        );
    }
}
