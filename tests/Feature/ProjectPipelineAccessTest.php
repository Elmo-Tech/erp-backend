<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $user = $this->userWithPermissions(['view_tasks', 'create_main_tasks', 'assign_tasks']);
        $user->update(['is_employee' => true, 'is_active' => true]);
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

    public function test_selected_employees_are_assigned_and_added_to_the_project(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'create_main_tasks', 'assign_tasks']);
        $employees = User::factory()->count(2)->create([
            'is_employee' => true,
            'is_active' => true,
        ]);
        foreach ($employees as $employee) {
            $employee->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));
        }
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $backlog = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", [
                'workflowStageId' => $backlog->id,
                'title' => 'Direct employee assignment',
                'assignedUserIds' => $employees->pluck('id')->all(),
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.assignees')
            ->assertJsonPath('data.assignedTo', $employees->first()->id);

        $taskId = $response->json('data.taskId');
        foreach ($employees as $index => $employee) {
            $this->assertDatabaseHas('project_members', [
                'project_id' => $project->id,
                'user_id' => $employee->id,
            ]);
            $this->assertDatabaseHas('task_assignees', [
                'task_id' => $taskId,
                'user_id' => $employee->id,
                'is_primary' => $index === 0,
            ]);
        }

        $this->actingAs($employees->first(), 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/pipeline")
            ->assertOk()
            ->assertJsonPath('data.project.projectId', $project->id);
    }

    public function test_assigning_pipeline_employees_requires_assignment_permission(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'create_main_tasks']);
        $employee = User::factory()->create([
            'is_employee' => true,
            'is_active' => true,
        ]);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $backlog = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", [
                'workflowStageId' => $backlog->id,
                'title' => 'Unauthorized assignment',
                'assignedUserIds' => [$employee->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $project->id,
            'user_id' => $employee->id,
        ]);
    }

    public function test_pipeline_assignees_must_be_active_employees(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'create_main_tasks', 'assign_tasks']);
        $inactiveEmployee = User::factory()->create([
            'is_employee' => true,
            'is_active' => false,
        ]);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $backlog = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", [
                'workflowStageId' => $backlog->id,
                'title' => 'Inactive employee assignment',
                'assignedUserIds' => [$inactiveEmployee->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignedUserIds');
    }

    public function test_authorized_member_can_edit_pipeline_task_and_replace_assignees(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions([
            'view_tasks',
            'create_main_tasks',
            'edit_main_tasks',
            'assign_tasks',
        ]);
        $assignee = User::factory()->create([
            'is_employee' => true,
            'is_active' => true,
        ]);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $backlog = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();
        $review = $project->workflowStages()->where('category', 'review')->firstOrFail();

        $taskId = $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", [
                'workflowStageId' => $backlog->id,
                'title' => 'Original task',
            ])
            ->assertCreated()
            ->json('data.taskId');

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$taskId}", [
                'workflowStageId' => $review->id,
                'title' => 'Updated task',
                'description' => 'Updated from the board',
                'priority' => 'urgent',
                'assignedUserIds' => [$assignee->id],
                'dueDate' => '2026-08-15',
                'estimatedHours' => 6.5,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated task')
            ->assertJsonPath('data.workflowStageId', $review->id)
            ->assertJsonPath('data.assignees.0.userId', $assignee->id)
            ->assertJsonPath('data.dueDate', '2026-08-15');

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'workflow_stage_id' => $review->id,
            'title' => 'Updated task',
            'assigned_to' => $assignee->id,
        ]);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $assignee->id,
        ]);
    }

    public function test_editing_pipeline_assignees_requires_assignment_permission(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'create_main_tasks', 'edit_main_tasks']);
        $assignee = User::factory()->create([
            'is_employee' => true,
            'is_active' => true,
        ]);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $backlog = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();
        $task = Task::factory()->for($project)->create([
            'workflow_stage_id' => $backlog->id,
            'is_pipeline_task' => true,
        ]);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$task->id}", [
                'title' => 'Unauthorized assignment edit',
                'assignedUserIds' => [$assignee->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('task_assignees', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
        ]);
    }

    public function test_pipeline_task_delete_requires_permission_and_is_scoped_to_project(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks']);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $otherProject->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();
        $task = Task::factory()->for($project)->create([
            'workflow_stage_id' => $stage->id,
            'is_pipeline_task' => true,
        ]);

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$task->id}")
            ->assertForbidden();

        $actor->givePermissionTo(Permission::findOrCreate('delete_tasks', 'web'));

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/projects/{$otherProject->id}/pipeline/tasks/{$task->id}")
            ->assertNotFound();

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$task->id}")
            ->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_authorized_member_can_replace_pipeline_task_cover(): void
    {
        Storage::fake('public');
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'edit_main_tasks']);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();
        $task = Task::factory()->for($project)->create([
            'workflow_stage_id' => $stage->id,
            'is_pipeline_task' => true,
            'cover_image_path' => "projects/{$project->id}/tasks/old-cover.jpg",
        ]);
        Storage::disk('public')->put($task->cover_image_path, 'old cover');

        $response = $this->actingAs($actor, 'sanctum')
            ->post("/api/v1/projects/{$project->id}/pipeline/tasks/{$task->id}/cover", [
                'coverImage' => UploadedFile::fake()->image('cover.jpg', 1200, 675),
            ])
            ->assertOk();

        $newCoverPath = Task::findOrFail($task->id)->cover_image_path;
        Storage::disk('public')->assertExists($newCoverPath);
        Storage::disk('public')->assertMissing("projects/{$project->id}/tasks/old-cover.jpg");
        $response->assertJsonPath(
            'data.coverImageUrl',
            Storage::disk('public')->url($newCoverPath)
        );
    }

    public function test_authorized_member_can_remove_pipeline_task_cover(): void
    {
        Storage::fake('public');
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'edit_main_tasks']);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();
        $coverPath = "projects/{$project->id}/tasks/cover.jpg";
        $task = Task::factory()->for($project)->create([
            'workflow_stage_id' => $stage->id,
            'is_pipeline_task' => true,
            'cover_image_path' => $coverPath,
        ]);
        Storage::disk('public')->put($coverPath, 'cover');

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$task->id}/cover")
            ->assertOk()
            ->assertJsonPath('data.coverImageUrl', null);

        Storage::disk('public')->assertMissing($coverPath);
        $this->assertNull(Task::findOrFail($task->id)->cover_image_path);
    }

    public function test_pipeline_task_cover_requires_edit_permission(): void
    {
        Storage::fake('public');
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks']);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();
        $task = Task::factory()->for($project)->create([
            'workflow_stage_id' => $stage->id,
            'is_pipeline_task' => true,
        ]);

        $this->actingAs($actor, 'sanctum')
            ->post("/api/v1/projects/{$project->id}/pipeline/tasks/{$task->id}/cover", [
                'coverImage' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->assertForbidden();

        $this->assertNull(Task::findOrFail($task->id)->cover_image_path);
    }

    public function test_pipeline_task_cover_is_scoped_to_project(): void
    {
        Storage::fake('public');
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'edit_main_tasks']);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $otherProject->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();
        $task = Task::factory()->for($project)->create([
            'workflow_stage_id' => $stage->id,
            'is_pipeline_task' => true,
        ]);

        $this->actingAs($actor, 'sanctum')
            ->post("/api/v1/projects/{$otherProject->id}/pipeline/tasks/{$task->id}/cover", [
                'coverImage' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->assertNotFound();

        $this->assertNull(Task::findOrFail($task->id)->cover_image_path);
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
