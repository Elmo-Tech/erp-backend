<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectPipelineAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_member_can_view_pipeline_but_outsider_cannot(): void
    {
        $project = Project::factory()->create();
        $member = $this->userWithPermissions(['view_tasks']);
        $outsider = $this->userWithPermissions(['view_tasks']);
        $project->members()->create(['user_id' => $member->id, 'role' => 'member']);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/pipeline")
            ->assertOk()
            ->assertJsonCount(7, 'data.stages');

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/pipeline")
            ->assertNotFound();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/tasks")
            ->assertForbidden();
    }

    public function test_view_all_projects_permission_bypasses_membership_scope(): void
    {
        $project = Project::factory()->create();
        $viewer = $this->userWithPermissions(['view_tasks', 'view_all_projects']);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/pipeline")
            ->assertOk();
    }

    public function test_pipeline_task_is_flat_and_legacy_tasks_are_untouched(): void
    {
        $project = Project::factory()->create();
        $user = $this->userWithPermissions(['view_tasks', 'create_main_tasks']);
        $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $legacyTask = Task::factory()->for($project)->create([
            'is_pipeline_task' => false,
        ]);
        $backlog = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", [
                'workflowStageId' => $backlog->id,
                'title' => 'Flat delivery task',
                'priority' => 'high',
                'assignedTo' => $user->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Flat delivery task');

        $pipelineTask = Task::findOrFail($response->json('data.taskId'));
        $this->assertTrue($pipelineTask->is_pipeline_task);
        $this->assertNull($pipelineTask->parent_id);
        $this->assertSame($backlog->id, $pipelineTask->workflow_stage_id);
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $pipelineTask->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        $legacyTask->refresh();
        $this->assertFalse($legacyTask->is_pipeline_task);
        $this->assertNull($legacyTask->workflow_stage_id);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/tasks")
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.taskId', $legacyTask->id);
    }

    public function test_pipeline_task_assignee_must_be_project_member(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'create_main_tasks']);
        $outsider = User::factory()->create();
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $backlog = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", [
                'workflowStageId' => $backlog->id,
                'title' => 'Protected assignment',
                'assignedTo' => $outsider->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignedTo');
    }

    public function test_outsider_cannot_create_a_legacy_task_inside_a_hidden_project(): void
    {
        $project = Project::factory()->create();
        $outsider = $this->userWithPermissions(['view_tasks', 'create_main_tasks']);

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'projectId' => $project->id,
                'title' => 'Hidden project task',
                'type' => 'main',
                'priority' => 'medium',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $project->id,
            'title' => 'Hidden project task',
        ]);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}
