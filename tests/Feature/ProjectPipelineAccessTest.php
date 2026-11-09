<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectPipelineAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginated_tasks_with_json_references_and_multiple_assignees_are_returned_once(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'view_all_projects']);
        $one = User::factory()->create();
        $two = User::factory()->create();
        $task = Task::factory()->for($project)->create(['reference_images' => ['projects/test/reference.png']]);
        foreach ([$one, $two] as $user) {
            $task->assignees()->create(['user_id' => $user->id, 'role' => 'developer', 'is_primary' => false]);
        }
        $response = $this->actingAs($actor, 'sanctum')->getJson('/api/v1/tasks?page=1&perPage=25')->assertOk();
        $this->assertSame(1, $response->json('data.pagination.total'));
        $response->assertJsonCount(1, 'data.tasks')->assertJsonPath('data.tasks.0.taskId', $task->id);
        $this->getJson('/api/v1/tasks?page=2&perPage=1')->assertOk()->assertJsonCount(0, 'data.tasks');
    }

    public function test_dashboard_tasks_include_all_visible_projects_and_actual_workflow_names(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $archived = Project::factory()->create();
        $member = $this->userWithPermissions(['view_tasks', 'view_all_tasks']);
        $admin = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'view_all_projects', 'manage_tasks']);
        $project->members()->create(['user_id' => $member->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('slug', 'backlog')->firstOrFail();
        $stage->update(['name' => 'Client Review']);
        $visible = Task::factory()->for($project)->create(['title' => 'Visible board task', 'is_pipeline_task' => true, 'workflow_stage_id' => $stage->id]);
        Task::factory()->count(101)->for($project)->create();
        $hidden = Task::factory()->for($otherProject)->create(['title' => 'Other project task']);
        Task::factory()->for($archived)->create(['title' => 'Archived project task']);
        $archived->delete();
        $response = $this->actingAs($member, 'sanctum')->getJson('/api/v1/tasks/dashboard-list')->assertOk()->json('data');
        $this->assertContains($visible->id, array_column($response, 'taskId'));
        $this->assertCount(102, $response);
        $this->assertNotContains($hidden->id, array_column($response, 'taskId'));
        $this->assertSame('Client Review', collect($response)->firstWhere('taskId', $visible->id)['status']);
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/tasks/dashboard-list')->assertOk()->json('data');
        $this->assertContains($hidden->id, array_column($response, 'taskId'));
        $this->assertNotContains('Archived project task', array_column($response, 'title'));
        $unauthorized = User::factory()->create();
        $this->actingAs($unauthorized, 'sanctum')->getJson('/api/v1/tasks/dashboard-list')->assertForbidden();
    }

    public function test_project_member_can_view_pipeline_but_outsider_cannot(): void
    {
        $project = Project::factory()->create();
        $member = $this->userWithPermissions(['view_tasks', 'view_all_tasks']);
        $outsider = $this->userWithPermissions(['view_tasks', 'view_all_tasks']);
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
        $viewer = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'view_all_projects']);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/pipeline")
            ->assertOk();
    }

    public function test_reordering_in_same_stage_preserves_status_timers_and_completion(): void
    {
        $project = Project::factory()->create();
        $user = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'edit_main_tasks', 'view_all_projects']);
        $stage = $project->workflowStages()->where('category', 'done')->firstOrFail();
        $done = now()->subDay()->startOfSecond();
        $tasks = collect(range(0, 2))->map(fn ($i) => Task::factory()->for($project)->create([
            'is_pipeline_task' => true, 'workflow_stage_id' => $stage->id, 'pipeline_position' => $i,
            'status' => 'completed', 'completed_at' => $done,
        ]));
        $timer = TimeEntry::factory()->active()->create(['user_id' => $user->id, 'trackable_type' => Task::class, 'trackable_id' => $tasks[2]->id, 'start_time' => now()->subHour()]);
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$tasks[2]->id}/stage", ['workflowStageId' => $stage->id, 'position' => 0])->assertOk();
        $this->assertSame([$tasks[2]->id, $tasks[0]->id, $tasks[1]->id], $stage->tasks()->pluck('id')->all());
        $this->assertTrue($tasks[2]->fresh()->completed_at->equalTo($done));
        $this->assertNull($timer->fresh()->end_time);
        $this->patchJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$tasks[2]->id}/stage", ['workflowStageId' => $stage->id, 'position' => 2])->assertOk();
        $this->assertSame($tasks->pluck('id')->all(), $stage->tasks()->pluck('id')->all());
    }

    public function test_move_between_stages_inserts_at_target_position_and_preserves_existing_order(): void
    {
        $project = Project::factory()->create();
        $user = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'edit_main_tasks', 'view_all_projects']);
        $from = $project->workflowStages()->where('category', 'planned')->firstOrFail();
        $to = $project->workflowStages()->where('category', 'active')->firstOrFail();
        $first = Task::factory()->for($project)->create(['is_pipeline_task' => true, 'workflow_stage_id' => $to->id, 'pipeline_position' => 10]);
        $second = Task::factory()->for($project)->create(['is_pipeline_task' => true, 'workflow_stage_id' => $to->id, 'pipeline_position' => 20]);
        $moving = Task::factory()->for($project)->create(['is_pipeline_task' => true, 'workflow_stage_id' => $from->id, 'pipeline_position' => 100, 'status' => 'todo']);
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$moving->id}/stage", ['workflowStageId' => $to->id, 'position' => 1])->assertOk();
        $this->assertSame([$first->id, $moving->id, $second->id], $to->tasks()->pluck('id')->all());
        $this->assertSame('in_progress', $moving->fresh()->status->value);
    }

    public function test_pipeline_task_is_flat_and_legacy_tasks_are_untouched(): void
    {
        $project = Project::factory()->create();
        $user = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'create_main_tasks', 'assign_tasks']);
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
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'create_main_tasks', 'assign_tasks']);
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
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'create_main_tasks']);
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
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'create_main_tasks', 'assign_tasks']);
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
            'view_all_tasks',
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
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'create_main_tasks', 'edit_main_tasks']);
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
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks']);
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
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'edit_main_tasks']);
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
                'coverImage' => UploadedFile::fake()->image('cover.jpg', 1200, 675)->size(6144),
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

    public function test_reference_images_can_be_added_opened_and_removed_by_authorized_project_members(): void
    {
        Storage::fake('public');
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'edit_main_tasks']);
        $viewer = $this->userWithPermissions(['view_tasks', 'view_all_tasks']);
        foreach ([$actor, $viewer] as $user) $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $task = Task::factory()->for($project)->create(['is_pipeline_task' => true]);
        $url = "/api/v1/projects/{$project->id}/pipeline/tasks/{$task->id}/references";
        $this->actingAs($actor, 'sanctum')->post($url, ['images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.png')]])->assertOk();
        $paths = $task->fresh()->reference_images;
        $this->assertCount(2, $paths);
        Storage::disk('public')->assertExists($paths[0]);
        $this->actingAs($viewer, 'sanctum')->getJson("/api/v1/tasks/{$task->id}")->assertOk()
            ->assertJsonPath('data.referenceImages.0.url', Storage::disk('public')->url($paths[0]))->assertJsonCount(2, 'data.referenceImages');
        $this->post($url, ['images' => [UploadedFile::fake()->image('third.jpg')]])->assertForbidden();
        $task->update(['reference_images' => array_fill(0, 10, 'already-saved.jpg')]);
        $this->actingAs($actor, 'sanctum')->post($url, ['images' => [UploadedFile::fake()->image('extra.jpg')]], ['Accept' => 'application/json'])->assertUnprocessable();
        $task->update(['reference_images' => $paths]);
        $this->actingAs($actor, 'sanctum')->deleteJson($url.'/'.basename($paths[0]))->assertOk();
        Storage::disk('public')->assertMissing($paths[0]);
        $this->assertCount(1, $task->fresh()->reference_images);
    }

    public function test_authorized_member_can_remove_pipeline_task_cover(): void
    {
        Storage::fake('public');
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'edit_main_tasks']);
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
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks']);
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
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'edit_main_tasks']);
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
        $outsider = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'create_main_tasks']);

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

    public function test_pipeline_supervisor_is_saved_atomically_and_added_to_project(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'create_main_tasks', 'edit_main_tasks', 'manage_task_team']);
        $supervisor = User::factory()->create(['is_employee' => true, 'is_active' => true]);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('category', 'backlog')->firstOrFail();

        $taskId = $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", [
                'workflowStageId' => $stage->id, 'title' => 'Supervised delivery', 'supervisorId' => $supervisor->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.supervisorId', $supervisor->id)
            ->assertJsonPath('data.supervisor.name', $supervisor->name)
            ->assertJsonCount(0, 'data.assignees')
            ->json('data.taskId');

        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $supervisor->id]);
        $this->assertDatabaseHas('task_activities', ['task_id' => $taskId, 'type' => 'supervisor_changed']);
        $this->assertDatabaseCount('time_entries', 0);

        $replacement = User::factory()->create(['is_employee' => true, 'is_active' => true]);
        $this->patchJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$taskId}", ['supervisorId' => $replacement->id])
            ->assertOk()->assertJsonPath('data.supervisor.name', $replacement->name);
        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $replacement->id]);

        $this->patchJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$taskId}", ['supervisorId' => null])
            ->assertOk()->assertJsonPath('data.supervisor', null);
    }

    public function test_pipeline_supervisor_assignment_requires_team_management_permission(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'create_main_tasks', 'edit_main_tasks']);
        $supervisor = User::factory()->create(['is_employee' => true, 'is_active' => true]);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('category', 'backlog')->firstOrFail();

        $this->actingAs($actor, 'sanctum')->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", [
            'workflowStageId' => $stage->id, 'title' => 'Unauthorized supervision', 'supervisorId' => $supervisor->id,
        ])->assertForbidden();

        $task = Task::factory()->for($project)->create(['workflow_stage_id' => $stage->id, 'is_pipeline_task' => true]);
        $this->patchJson("/api/v1/projects/{$project->id}/pipeline/tasks/{$task->id}", ['supervisorId' => $supervisor->id])->assertForbidden();
        $this->assertNull($task->fresh()->supervisor_id);
        $this->assertDatabaseMissing('project_members', ['project_id' => $project->id, 'user_id' => $supervisor->id]);
    }

    public function test_invalid_pipeline_supervisor_rolls_back_task_and_assignee_membership(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks', 'create_main_tasks', 'assign_tasks', 'manage_task_team']);
        $supervisor = User::factory()->create(['is_employee' => true, 'is_active' => false]);
        $assignee = User::factory()->create(['is_employee' => true, 'is_active' => true]);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('category', 'backlog')->firstOrFail();

        $this->actingAs($actor, 'sanctum')->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", [
            'workflowStageId' => $stage->id, 'title' => 'Invalid supervisor', 'supervisorId' => $supervisor->id,
            'assignedUserIds' => [$assignee->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('supervisorId');

        $this->assertDatabaseMissing('tasks', ['project_id' => $project->id, 'title' => 'Invalid supervisor']);
        $this->assertDatabaseMissing('project_members', ['project_id' => $project->id, 'user_id' => $assignee->id]);
    }

    public function test_pipeline_card_payload_includes_logged_time_and_named_active_work_sessions(): void
    {
        $project = Project::factory()->create();
        $actor = $this->userWithPermissions(['view_tasks', 'view_all_tasks']);
        $project->members()->create(['user_id' => $actor->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('category', 'active')->firstOrFail();
        $task = Task::factory()->for($project)->create([
            'workflow_stage_id' => $stage->id, 'is_pipeline_task' => true,
            'supervisor_id' => $actor->id,
        ]);
        TimeEntry::factory()->create([
            'user_id' => $actor->id, 'trackable_id' => $task->id, 'trackable_type' => Task::class,
            'start_time' => now()->subMinutes(100), 'end_time' => now()->subMinutes(5), 'duration_minutes' => 95,
        ]);
        $entry = TimeEntry::factory()->active()->create([
            'user_id' => $actor->id, 'trackable_id' => $task->id, 'trackable_type' => Task::class,
            'start_time' => now()->subMinute(),
        ]);

        $response = $this->actingAs($actor, 'sanctum')->getJson("/api/v1/projects/{$project->id}/pipeline")->assertOk();
        $payload = collect($response->json('data.stages'))->flatMap(fn ($item) => $item['tasks'])->firstWhere('taskId', $task->id);
        $this->assertSame(95, $payload['totalTimeSpent']);
        $this->assertSame($actor->name, $payload['supervisor']['name']);
        $this->assertCount(1, $payload['activeWorkTimers']);
        $this->assertSame($actor->id, $payload['activeWorkTimers'][0]['userId']);
        $this->assertSame($actor->name, $payload['activeWorkTimers'][0]['name']);
        $this->assertSame($entry->start_time->toIso8601String(), $payload['activeWorkTimers'][0]['startTime']);
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
