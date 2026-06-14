<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectTaskApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_task_index_returns_flat_contract_without_legacy_subtask_fields(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'manage_all_projects', 'view_tasks']);
        $project = Project::factory()->create();

        collect(range(1, 3))->each(function (int $index) use ($project, $user): void {
            $task = ProjectTask::create([
                'project_id' => $project->id,
                'title' => "Execution task {$index}",
                'status' => 'to_do',
                'assignee_id' => $user->id,
            ]);

            ProjectTimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'start_time' => now()->subHours(2),
                'end_time' => now()->subHour(),
                'duration_minutes' => 60 + $index,
                'is_manual' => true,
            ]);
        });

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/tasks");

        $queryCount = count(DB::getQueryLog());

        $response->assertOk()
            ->assertJsonPath('data.tasks.0.title', 'Execution task 1')
            ->assertJsonPath('data.tasks.0.totalLoggedMinutes', 61);

        $this->assertSame([
            'taskId',
            'projectId',
            'milestoneId',
            'title',
            'description',
            'status',
            'statusLabel',
            'estimatedHours',
            'startDate',
            'dueDate',
            'assigneeId',
            'isInBacklog',
            'totalLoggedMinutes',
            'assignee',
            'milestone',
            'deliverables',
            'timeEntries',
            'createdAt',
            'updatedAt',
        ], array_keys($response->json('data.tasks.0')));

        $this->assertLessThanOrEqual(8, $queryCount);
    }

    public function test_project_task_update_persists_explicit_nullable_fields(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'manage_all_projects', 'view_tasks', 'manage_tasks']);
        $project = Project::factory()->create();
        $milestone = Milestone::create([
            'project_id' => $project->id,
            'title' => 'Phase 1',
            'status' => 'pending',
        ]);
        $assignee = User::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'title' => 'Existing task',
            'description' => 'Existing description',
            'status' => 'to_do',
            'estimated_hours' => 4,
            'start_date' => '2026-06-01',
            'due_date' => '2026-06-05',
            'assignee_id' => $assignee->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
                'title' => 'Updated task',
                'description' => null,
                'milestoneId' => null,
                'estimatedHours' => null,
                'startDate' => null,
                'dueDate' => null,
                'assigneeId' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated task')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.milestoneId', null)
            ->assertJsonPath('data.estimatedHours', null)
            ->assertJsonPath('data.startDate', null)
            ->assertJsonPath('data.dueDate', null)
            ->assertJsonPath('data.assigneeId', null);

        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'title' => 'Updated task',
            'description' => null,
            'milestone_id' => null,
            'estimated_hours' => null,
            'start_date' => null,
            'due_date' => null,
            'assignee_id' => null,
        ]);

        $this->assertDatabaseHas('project_histories', [
            'project_id' => $project->id,
            'field_name' => 'task_assignee_changed',
            'old_value' => $assignee->name,
            'new_value' => 'Unassigned',
        ]);
    }

    public function test_assigned_worker_can_update_task_status_without_metadata_permission(): void
    {
        $worker = $this->userWithPermissions(['view_projects', 'view_tasks', 'work_on_tasks']);
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $worker->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Assigned task',
            'status' => 'to_do',
            'assignee_id' => $worker->id,
        ]);

        $response = $this->actingAs($worker, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
                'status' => 'in_review',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => 'in_review',
            'title' => 'Assigned task',
        ]);
    }

    public function test_assigned_worker_cannot_update_task_metadata_without_edit_permission(): void
    {
        $worker = $this->userWithPermissions(['view_projects', 'view_tasks', 'work_on_tasks']);
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $worker->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Assigned task',
            'status' => 'to_do',
            'assignee_id' => $worker->id,
        ]);

        $response = $this->actingAs($worker, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
                'title' => 'Unauthorized metadata update',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'title' => 'Assigned task',
        ]);
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
