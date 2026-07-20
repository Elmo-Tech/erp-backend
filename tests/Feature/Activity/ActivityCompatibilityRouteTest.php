<?php

namespace Tests\Feature\Activity;

use App\Modules\Client\Models\Client;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityCompatibilityRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_schedules_a_meeting_through_the_activity_route(): void
    {
        Permission::firstOrCreate(['name' => 'schedule_meetings']);
        Permission::firstOrCreate(['name' => 'view_clients']);

        $user = User::factory()->create();
        $user->givePermissionTo(['schedule_meetings', 'view_clients']);

        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Acme',
            'primary_email' => 'acme-activity@example.com',
            'lifecycle_stage' => 'client',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/activities', [
                'subjectType' => Client::class,
                'subjectId' => $client->id,
                'type' => 'meeting',
                'title' => 'Discovery Meeting',
                'description' => 'Location Type: Online',
                'scheduledAt' => now()->addDay()->toISOString(),
                'assignedTo' => $user->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Discovery Meeting');

        $this->assertDatabaseHas('meetings', [
            'model_type' => Client::class,
            'model_id' => $client->id,
            'title' => 'Discovery Meeting',
        ]);

        $this->assertDatabaseHas('client_activities', [
            'client_id' => $client->id,
            'action' => 'Meeting Scheduled',
        ]);
    }
}
