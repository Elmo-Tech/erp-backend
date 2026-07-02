<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ChangeRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QaReleaseGateFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_finds_projects_by_title(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $project = Project::factory()->create(['title' => 'Atlas Delivery Portal']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/search?q=Atlas')
            ->assertOk()
            ->assertJsonPath('data.results.projects.0.id', $project->id)
            ->assertJsonPath('data.results.projects.0.title', 'Atlas Delivery Portal');
    }

    public function test_client_change_request_notifies_project_member(): void
    {
        Notification::fake();

        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $clientUser = User::factory()->create();
        $clientUser->assignRole($clientRole);
        $client = $this->ensureClientProfile($clientUser);

        $manager = User::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $project->members()->create(['user_id' => $manager->id, 'role' => 'lead']);

        $this->actingAs($clientUser, 'sanctum')->postJson("/api/v1/projects/{$project->id}/change-requests", [
            'title' => 'Need updated copy',
            'description' => 'Please update the homepage copy.',
        ])->assertCreated();

        Notification::assertSentTo($manager, ChangeRequestSubmitted::class, function (ChangeRequestSubmitted $notification) use ($manager, $project) {
            $payload = $notification->toArray($manager);

            return $payload['project_id'] === $project->id
                && str_contains($payload['url'], '/dashboard/projects/'.$project->id.'?tab=change-requests&changeRequest=');
        });
    }
}
