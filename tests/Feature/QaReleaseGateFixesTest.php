<?php

namespace Tests\Feature;

use App\Models\LookupOption;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectHistory;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ChangeRequestSubmitted;
use App\Notifications\UserAddedToProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
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
        $project->members()->create(['user_id' => $admin->id, 'role' => 'member']);

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

    public function test_adding_project_member_sends_notification_to_added_user(): void
    {
        Notification::fake();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $manager = User::factory()->create(['name' => 'Project Manager']);
        $manager->assignRole($adminRole);
        $manager->givePermissionTo(Permission::findOrCreate('add_project_members', 'web'));
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $manager->id, 'role' => 'lead']);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/members", [
                'userId' => $member->id,
                'role' => 'member',
            ])
            ->assertCreated();

        Notification::assertSentTo($member, UserAddedToProject::class, function (UserAddedToProject $notification) use ($manager, $member, $project) {
            $payload = $notification->toArray($member);

            return $payload['project_id'] === $project->id
                && $payload['added_by_id'] === $manager->id
                && $payload['role'] === 'member';
        });
    }

    public function test_project_deadline_update_records_one_history_entry_with_reason(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $manager = User::factory()->create();
        $manager->assignRole($adminRole);
        $manager->givePermissionTo(Permission::findOrCreate('manage_projects', 'web'));
        $project = Project::factory()->create([
            'deadline' => now()->addDays(10)->toDateString(),
        ]);
        $project->members()->create(['user_id' => $manager->id, 'role' => 'lead']);
        $newDeadline = now()->addDays(20)->toDateString();

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/projects/{$project->id}", [
                'title' => $project->title,
                'deadline' => $newDeadline,
                'status' => $project->status->value,
                'reason' => 'Client approved a new delivery date',
            ])
            ->assertOk();

        $histories = ProjectHistory::query()
            ->where('project_id', $project->id)
            ->where('field_name', 'deadline')
            ->get();

        $this->assertCount(1, $histories);
        $this->assertSame('Client approved a new delivery date', $histories->first()->reason);
        $this->assertSame($newDeadline, $histories->first()->new_value);
    }

    public function test_project_file_can_be_replaced_and_recorded_in_history(): void
    {
        Storage::fake('public');
        Permission::firstOrCreate(['name' => 'manage_project_files']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $manager = User::factory()->create();
        $manager->assignRole($adminRole);
        $manager->givePermissionTo('manage_project_files');
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $manager->id, 'role' => 'lead']);
        $file = ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'old-brief.pdf',
            'type' => 'file',
            'file_path' => UploadedFile::fake()->create('old-brief.pdf', 10)->store("projects/{$project->id}/files", 'public'),
            'mime_type' => 'application/pdf',
            'size' => 10,
            'category' => 'document',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->post("/api/v1/projects/{$project->id}/files/{$file->id}/replace", [
                'file' => UploadedFile::fake()->create('new-brief.pdf', 12, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'new-brief.pdf');

        $file->refresh();
        $this->assertSame('new-brief.pdf', $file->name);
        Storage::disk('public')->assertExists($file->file_path);

        $this->assertDatabaseHas('project_histories', [
            'project_id' => $project->id,
            'field_name' => 'project_file',
            'old_value' => 'old-brief.pdf',
            'new_value' => 'new-brief.pdf',
        ]);
    }
}
