<?php

namespace Tests\Feature\Security;

use App\Enums\ProjectRole;
use App\Modules\Client\Models\Client;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\ChangeRequest;
use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectFile;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTaskDeliverable;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Project\Models\ProjectWiki;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StrictDeletePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_destroy_requires_delete_clients_permission(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithPermissions(), 'sanctum')
            ->deleteJson("/api/v1/clients/{$client->id}")
            ->assertForbidden();

        $allowedUser = $this->userWithPermissions(['delete_clients']);

        $this->actingAs($allowedUser, 'sanctum')
            ->deleteJson("/api/v1/clients/{$client->id}")
            ->assertOk();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_meeting_destroy_requires_delete_meetings_permission(): void
    {
        $client = Client::factory()->create();
        $meeting = Meeting::create([
            'model_id' => $client->id,
            'model_type' => Client::class,
            'status' => 'scheduled',
            'title' => 'Security review',
        ]);

        $this->actingAs($this->userWithPermissions(['schedule_meetings']), 'sanctum')
            ->deleteJson("/api/v1/meetings/{$meeting->id}")
            ->assertForbidden();

        $allowedUser = $this->userWithPermissions(['schedule_meetings', 'delete_meetings']);

        $this->actingAs($allowedUser, 'sanctum')
            ->deleteJson("/api/v1/meetings/{$meeting->id}")
            ->assertOk();

        $this->assertSoftDeleted('meetings', ['id' => $meeting->id]);
    }

    public function test_milestone_destroy_requires_delete_milestones_permission(): void
    {
        [$project, $milestone] = $this->projectWithMilestone();

        $this->actingAs($this->userWithPermissions(['manage_projects']), 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/milestones/{$milestone->id}")
            ->assertForbidden();

        $allowedUser = $this->userWithPermissions(['manage_projects', 'delete_milestones']);

        $this->actingAs($allowedUser, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/milestones/{$milestone->id}")
            ->assertOk();

        $this->assertSoftDeleted('milestones', ['id' => $milestone->id]);
    }

    public function test_task_destroy_requires_delete_tasks_permission(): void
    {
        [$project, $task] = $this->projectWithTask();

        $this->actingAs($this->userWithPermissions(['manage_tasks']), 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
            ->assertForbidden();

        $allowedUser = $this->userWithPermissions(['delete_tasks']);

        $this->actingAs($allowedUser, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
            ->assertOk();

        $this->assertSoftDeleted('project_tasks', ['id' => $task->id]);
    }

    public function test_task_deliverable_destroy_requires_delete_task_deliverables_permission(): void
    {
        [$project, $task] = $this->projectWithTask();
        $deliverable = ProjectTaskDeliverable::create([
            'task_id' => $task->id,
            'title' => 'Review checklist',
            'is_completed' => false,
        ]);

        $this->actingAs($this->userWithPermissions(['manage_tasks']), 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/deliverables/{$deliverable->id}")
            ->assertForbidden();

        $allowedUser = $this->userWithPermissions(['manage_tasks', 'delete_project_task_deliverables']);

        $this->actingAs($allowedUser, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/deliverables/{$deliverable->id}")
            ->assertOk();

        $this->assertSoftDeleted('project_task_deliverables', ['id' => $deliverable->id]);
    }

    public function test_time_entry_destroy_requires_creator_or_project_manager(): void
    {
        [$project, $task] = $this->projectWithTask();
        $creator = $this->userWithPermissions(['log_time']);
        $entry = ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $creator->id,
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'duration_minutes' => 60,
            'is_manual' => true,
        ]);

        $this->actingAs($this->userWithPermissions(['delete_time_entries']), 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}")
            ->assertForbidden();

        $this->actingAs($creator, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}")
            ->assertOk();

        $this->assertSoftDeleted('project_time_entries', ['id' => $entry->id]);

        $managedEntry = ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => User::factory()->create()->id,
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'duration_minutes' => 60,
            'is_manual' => true,
        ]);
        $manager = $this->userWithPermissions(['edit_tasks']);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER->value,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$managedEntry->id}")
            ->assertOk();

        $this->assertSoftDeleted('project_time_entries', ['id' => $managedEntry->id]);
    }

    public function test_change_request_destroy_requires_delete_change_requests_permission(): void
    {
        $project = Project::factory()->create();
        $changeRequest = ChangeRequest::create([
            'project_id' => $project->id,
            'title' => 'Scope update',
            'description' => 'Add one landing page.',
            'price' => 500,
            'status' => 'draft',
        ]);

        $this->actingAs($this->userWithPermissions(['manage_change_requests']), 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/change-requests/{$changeRequest->id}")
            ->assertForbidden();

        $allowedUser = $this->userWithPermissions(['manage_change_requests', 'delete_change_requests']);

        $this->actingAs($allowedUser, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/change-requests/{$changeRequest->id}")
            ->assertOk();

        $this->assertSoftDeleted('change_requests', ['id' => $changeRequest->id]);
    }

    public function test_dictionary_destroy_requires_delete_settings_permission(): void
    {
        $this->postJsonAs($this->userWithPermissions(), '/api/v1/settings/lead-sources', [
            'name' => 'Partner',
        ])->assertCreated();

        $id = (int) \DB::table('lead_sources')->where('slug', 'partner')->value('id');

        $this->actingAs($this->userWithPermissions(), 'sanctum')
            ->deleteJson("/api/v1/settings/lead-sources/{$id}")
            ->assertForbidden();

        $this->actingAs($this->userWithPermissions(['delete_settings']), 'sanctum')
            ->deleteJson("/api/v1/settings/lead-sources/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('lead_sources', ['id' => $id]);
    }

    public function test_project_file_and_wiki_models_soft_delete(): void
    {
        $project = Project::factory()->create();
        $file = ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'Brief',
            'type' => 'link',
            'url' => 'https://example.test/brief',
        ]);
        $wiki = ProjectWiki::create([
            'project_id' => $project->id,
            'title' => 'Runbook',
            'content' => 'Deployment notes',
        ]);

        $file->delete();
        $wiki->delete();

        $this->assertSoftDeleted('project_files', ['id' => $file->id]);
        $this->assertSoftDeleted('project_wikis', ['id' => $wiki->id]);
    }

    /**
     * @return array{0: Project, 1: Milestone}
     */
    private function projectWithMilestone(): array
    {
        $project = Project::factory()->create();
        $milestone = Milestone::create([
            'project_id' => $project->id,
            'title' => 'MVP',
            'status' => 'pending',
        ]);

        return [$project, $milestone];
    }

    /**
     * @return array{0: Project, 1: ProjectTask}
     */
    private function projectWithTask(): array
    {
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Build API',
            'status' => 'to_do',
        ]);

        return [$project, $task];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions = []): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postJsonAs(User $user, string $uri, array $payload): TestResponse
    {
        return $this->actingAs($user, 'sanctum')->postJson($uri, $payload);
    }
}
