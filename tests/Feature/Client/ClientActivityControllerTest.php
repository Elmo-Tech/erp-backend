<?php

namespace Tests\Feature\Client;

use App\Modules\Client\Models\ClientActivity;
use App\Modules\CRM\Models\Client;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_client_activity_timeline(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        ClientActivity::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'action' => 'Project Created',
            'description' => "Project 'Website' created by {$user->name}.",
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/clients/{$client->id}/activities");

        $response->assertOk()
            ->assertJsonPath('data.activities.0.action', 'Project Created')
            ->assertJsonPath('data.activities.0.user.name', $user->name);
    }

    public function test_it_logs_project_and_meeting_events_without_blocking_creation(): void
    {
        Permission::firstOrCreate(['name' => 'create_projects']);
        Permission::firstOrCreate(['name' => 'schedule_meetings']);

        $user = User::factory()->create();
        $user->givePermissionTo(['create_projects', 'schedule_meetings']);
        $client = Client::factory()->create(['display_name' => 'Acme']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/projects/direct', [
                'clientId' => $client->id,
                'title' => 'Website',
                'description' => 'Project Type: Web Development',
                'budget' => 0,
                'deadline' => now()->addMonth()->toDateString(),
                'status' => 'draft',
            ])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/activities', [
                'subjectType' => Client::class,
                'subjectId' => $client->id,
                'type' => 'meeting',
                'title' => 'Discovery Meeting',
                'description' => 'Location Type: Online',
                'scheduledAt' => now()->addDay()->toISOString(),
                'assignedTo' => $user->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('client_activities', [
            'client_id' => $client->id,
            'action' => 'Project Created',
        ]);

        $this->assertDatabaseHas('client_activities', [
            'client_id' => $client->id,
            'action' => 'Meeting Scheduled',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/clients/{$client->id}/projects")
            ->assertOk()
            ->assertJsonPath('data.projects.0.title', 'Website');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/clients/{$client->id}/meetings")
            ->assertOk()
            ->assertJsonPath('data.meetings.0.title', 'Discovery Meeting');
    }
}
