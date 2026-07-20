<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectTimeEntryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_requires_delete_permission_for_their_own_time_entry(): void
    {
        [$project, $task, $entry, $creator] = $this->timeEntryScenario();
        $creator->givePermissionTo(Permission::findOrCreate('view_projects', 'web'));
        $creator->givePermissionTo(Permission::findOrCreate('log_time', 'web'));

        $this->actingAs($creator, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('project_time_entries', ['id' => $entry->id, 'deleted_at' => null]);

        $creator->givePermissionTo(Permission::findOrCreate('delete_time_entries', 'web'));

        $this->actingAs($creator, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}")
            ->assertOk();

        $this->assertSoftDeleted('project_time_entries', ['id' => $entry->id]);
    }

    public function test_project_manager_requires_delete_permission_for_a_team_time_entry(): void
    {
        [$project, $task, $entry] = $this->timeEntryScenario();
        $manager = $this->userWithPermissions(['view_projects', 'edit_time_entries']);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER->value,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}")
            ->assertForbidden();

        $manager->givePermissionTo(Permission::findOrCreate('delete_time_entries', 'web'));

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}")
            ->assertOk();

        $this->assertSoftDeleted('project_time_entries', ['id' => $entry->id]);
    }

    public function test_non_creator_non_manager_cannot_delete_time_entry_even_with_delete_permission(): void
    {
        [$project, $task, $entry] = $this->timeEntryScenario();
        $outsider = $this->userWithPermissions(['delete_time_entries']);

        $this->actingAs($outsider, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}")
            ->assertForbidden();
    }

    public function test_update_requires_edit_permission_independently_from_delete_permission(): void
    {
        [$project, $task, $entry, $creator] = $this->timeEntryScenario();
        $creator->givePermissionTo(Permission::findOrCreate('view_projects', 'web'));
        $creator->givePermissionTo(Permission::findOrCreate('delete_time_entries', 'web'));
        $payload = [
            'startTime' => now()->subMinutes(45)->toIso8601String(),
            'endTime' => now()->toIso8601String(),
            'note' => 'Corrected entry',
        ];

        $this->actingAs($creator, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}", $payload)
            ->assertForbidden();

        $this->assertDatabaseHas('project_time_entries', [
            'id' => $entry->id,
            'note' => null,
        ]);

        $creator->givePermissionTo(Permission::findOrCreate('edit_time_entries', 'web'));

        $this->actingAs($creator, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}", $payload)
            ->assertOk();

        $this->assertDatabaseHas('project_time_entries', [
            'id' => $entry->id,
            'note' => 'Corrected entry',
        ]);
    }

    /**
     * @return array{0: Project, 1: ProjectTask, 2: ProjectTimeEntry, 3: User}
     */
    private function timeEntryScenario(): array
    {
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Build API',
            'status' => 'to_do',
        ]);
        $creator = User::factory()->create();
        $entry = ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $creator->id,
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'duration_minutes' => 60,
            'is_manual' => true,
        ]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $creator->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        return [$project, $task, $entry, $creator];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
