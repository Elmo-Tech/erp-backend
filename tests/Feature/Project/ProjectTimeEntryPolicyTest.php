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

    public function test_creator_can_delete_their_own_time_entry(): void
    {
        [$project, $task, $entry, $creator] = $this->timeEntryScenario();
        $creator->givePermissionTo(Permission::findOrCreate('log_time', 'web'));

        $this->actingAs($creator, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}")
            ->assertOk();

        $this->assertSoftDeleted('project_time_entries', ['id' => $entry->id]);
    }

    public function test_project_manager_can_delete_a_team_time_entry(): void
    {
        [$project, $task, $entry] = $this->timeEntryScenario();
        $manager = $this->userWithPermissions(['edit_tasks']);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER->value,
        ]);

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
