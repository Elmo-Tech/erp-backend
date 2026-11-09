<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectTaskSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_list_counts_only_visible_tasks_and_groups_by_workflow_stage(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $todo = $project->workflowStages()->where('category', 'planned')->firstOrFail();
        $active = $project->workflowStages()->where('category', 'active')->firstOrFail();
        Task::factory()->for($project)->create(['assigned_to' => $user->id, 'workflow_stage_id' => $todo->id, 'is_pipeline_task' => true]);
        Task::factory()->for($project)->create(['supervisor_id' => $user->id, 'workflow_stage_id' => $active->id, 'is_pipeline_task' => true]);
        Task::factory()->for($project)->create(['workflow_stage_id' => $todo->id, 'is_pipeline_task' => true]);

        $row = $this->actingAs($user, 'sanctum')->getJson('/api/v1/projects')->assertOk()->json('data.projects.0');
        $this->assertSame(2, $row['taskSummary']['total']);
        $this->assertSame([$todo->name, $active->name], array_column($row['taskSummary']['statuses'], 'name'));
        $this->assertSame([1, 1], array_column($row['taskSummary']['statuses'], 'count'));

        $user->givePermissionTo(Permission::findOrCreate('view_all_tasks', 'web'));
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.projects.0.taskSummary.total', 3);
    }

    public function test_legacy_and_pipeline_tasks_with_same_status_are_combined(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('category', 'planned')->firstOrFail();
        Task::factory()->for($project)->create(['assigned_to' => $user->id, 'workflow_stage_id' => $stage->id, 'is_pipeline_task' => true, 'status' => 'todo']);
        Task::factory()->for($project)->create(['assigned_to' => $user->id, 'workflow_stage_id' => null, 'is_pipeline_task' => false, 'status' => 'todo']);
        $summary = $this->actingAs($user, 'sanctum')->getJson('/api/v1/projects')->assertOk()->json('data.projects.0.taskSummary');
        $this->assertSame(2, $summary['total']);
        $this->assertCount(1, $summary['statuses']);
        $this->assertSame(2, $summary['statuses'][0]['count']);
    }

    public function test_summary_is_omitted_without_task_view_permission(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/projects')->assertOk()->assertJsonMissingPath('data.projects.0.taskSummary');
    }
}
