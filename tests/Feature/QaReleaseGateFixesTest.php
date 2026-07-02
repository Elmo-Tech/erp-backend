<?php

namespace Tests\Feature;

use App\Models\LookupOption;
use App\Models\Project;
use App\Models\Task;
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

    public function test_dynamic_specialization_filters_tasks_from_lookup_metadata(): void
    {
        LookupOption::create([
            'type' => 'specialization',
            'value' => 'backend_dev',
            'label' => 'Backend Dev',
            'metadata' => ['compatible_task_types' => ['backend']],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['specialization' => 'backend_dev']);
        $backendTask = Task::factory()->create(['task_category' => 'backend']);
        $frontendTask = Task::factory()->create(['task_category' => 'frontend']);

        $taskIds = Task::query()->forUserSpecialization($user)->pluck('id')->all();

        $this->assertContains($backendTask->id, $taskIds);
        $this->assertNotContains($frontendTask->id, $taskIds);
    }
}
