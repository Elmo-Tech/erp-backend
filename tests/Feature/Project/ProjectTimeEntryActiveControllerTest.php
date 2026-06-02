<?php

namespace Tests\Feature\Project;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectTimeEntryActiveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_their_active_project_time_entry(): void
    {
        $user = $this->userWithPermissions(['view_projects']);
        $project = Project::factory()->create(['title' => 'Mobile App']);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Build timer',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);
        $entry = ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'start_time' => now()->subMinutes(25),
            'is_manual' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects/time-entries/active')
            ->assertOk()
            ->assertJsonPath('data.entryId', $entry->id)
            ->assertJsonPath('data.taskId', $task->id)
            ->assertJsonPath('data.projectId', $project->id)
            ->assertJsonPath('data.task.title', 'Build timer')
            ->assertJsonPath('data.project.title', 'Mobile App');
    }

    public function test_active_project_time_entry_returns_null_when_idle(): void
    {
        $user = $this->userWithPermissions(['view_projects']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects/time-entries/active')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_team_timer_start_requires_manage_team_timers_permission(): void
    {
        $manager = $this->userWithPermissions(['view_projects', 'manage_all_projects']);
        $target = User::factory()->create();
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Assigned work',
            'status' => 'in_progress',
            'assignee_id' => $target->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/start-for-user", [
                'userId' => $target->id,
            ])
            ->assertForbidden();
    }

    public function test_team_timer_start_for_user_creates_active_entry(): void
    {
        $manager = $this->userWithPermissions(['view_projects', 'manage_all_projects', 'manage_team_timers']);
        $target = User::factory()->create();
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Assigned work',
            'status' => 'in_progress',
            'assignee_id' => $target->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/start-for-user", [
                'userId' => $target->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.userId', $target->id)
            ->assertJsonPath('data.taskId', $task->id)
            ->assertJsonPath('data.isRunning', true);
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
