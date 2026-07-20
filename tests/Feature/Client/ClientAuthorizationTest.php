<?php

namespace Tests\Feature\Client;

use App\Enums\MeetingStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientActivity;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_directory_requires_client_visibility_permission(): void
    {
        $ordinaryUser = User::factory()->create();
        $viewer = $this->userWithPermission('view_clients');
        $client = Client::factory()->create();

        $this->actingAs($ordinaryUser, 'sanctum')
            ->getJson('/api/v1/clients')
            ->assertForbidden();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonPath('data.clients.0.id', $client->id);
    }

    public function test_client_profile_requires_client_visibility_permission(): void
    {
        $ordinaryUser = User::factory()->create();
        $viewer = $this->userWithPermission('view_clients');
        $client = Client::factory()->create();

        $this->actingAs($ordinaryUser, 'sanctum')
            ->getJson("/api/v1/clients/{$client->id}")
            ->assertForbidden();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.clientId', $client->id);
    }

    public function test_portal_user_can_read_only_their_minimized_client_profile(): void
    {
        [$portalUser, $ownClient, $foreignClient] = $this->portalFixture();

        $this->actingAs($portalUser, 'sanctum')
            ->getJson("/api/v1/clients/{$foreignClient->id}")
            ->assertForbidden();

        $this->actingAs($portalUser, 'sanctum')
            ->getJson("/api/v1/clients/{$ownClient->id}")
            ->assertOk()
            ->assertJsonPath('data.clientId', $ownClient->id)
            ->assertJsonMissingPath('data.notes')
            ->assertJsonMissingPath('data.taxNumber')
            ->assertJsonMissingPath('data.contacts')
            ->assertJsonMissingPath('data.userId');
    }

    public function test_client_activity_requires_internal_client_visibility(): void
    {
        $ordinaryUser = User::factory()->create();
        $viewer = $this->userWithPermission('view_clients');
        $client = Client::factory()->create();

        ClientActivity::create([
            'client_id' => $client->id,
            'user_id' => $viewer->id,
            'action' => 'Sensitive Activity',
            'description' => 'Internal client lifecycle detail.',
        ]);

        $this->actingAs($ordinaryUser, 'sanctum')
            ->getJson("/api/v1/clients/{$client->id}/activities")
            ->assertForbidden();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/clients/{$client->id}/activities")
            ->assertOk()
            ->assertJsonPath('data.activities.0.action', 'Sensitive Activity');
    }

    public function test_portal_project_collection_is_scoped_to_its_parent_client(): void
    {
        [$portalUser, $ownClient, $foreignClient] = $this->portalFixture();
        $ownProject = Project::factory()->create(['client_id' => $ownClient->id]);
        Project::factory()->create(['client_id' => $foreignClient->id]);

        $this->actingAs($portalUser, 'sanctum')
            ->getJson("/api/v1/clients/{$foreignClient->id}/projects")
            ->assertForbidden();

        $this->actingAs($portalUser, 'sanctum')
            ->getJson("/api/v1/clients/{$ownClient->id}/projects")
            ->assertOk()
            ->assertJsonPath('data.projects.0.projectId', $ownProject->id);
    }

    public function test_client_meeting_collection_requires_action_scope_and_caller_visibility(): void
    {
        [$portalUser, $ownClient, $foreignClient] = $this->portalFixture();

        $this->actingAs($portalUser, 'sanctum')
            ->getJson("/api/v1/clients/{$foreignClient->id}/meetings")
            ->assertForbidden();

        $this->actingAs($portalUser, 'sanctum')
            ->getJson("/api/v1/clients/{$ownClient->id}/meetings")
            ->assertForbidden();

        $viewer = $this->userWithPermission('view_clients');
        $viewer->givePermissionTo(Permission::findOrCreate('view_meetings', 'web'));
        $visibleMeeting = $this->meetingFor($ownClient, $viewer);
        $this->meetingFor($ownClient, User::factory()->create());

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/clients/{$ownClient->id}/meetings")
            ->assertOk()
            ->assertJsonCount(1, 'data.meetings')
            ->assertJsonPath('data.meetings.0.meetingId', $visibleMeeting->id);
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));

        return $user;
    }

    private function portalFixture(): array
    {
        $portalUser = $this->userWithPermission('access_client_portal');
        $portalUser->givePermissionTo(Permission::findOrCreate('view_projects', 'web'));
        $ownClient = Client::factory()->create(['user_id' => $portalUser->id]);
        $foreignClient = Client::factory()->create();

        return [$portalUser, $ownClient, $foreignClient];
    }

    private function meetingFor(Client $client, User $assignee): Meeting
    {
        return Meeting::create([
            'model_type' => Client::class,
            'model_id' => $client->id,
            'title' => "Meeting for {$client->id}",
            'status' => MeetingStatus::SCHEDULED,
            'assigned_to' => $assignee->id,
            'scheduled_at' => now()->addDay(),
        ]);
    }
}
