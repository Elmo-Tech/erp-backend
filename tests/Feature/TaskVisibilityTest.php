<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function actor(array $permissions): User
    {
        $user = User::factory()->create(['specialization' => null]);
        foreach ($permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        return $user;
    }

    public function test_member_sees_only_assigned_and_supervised_tasks_across_lists_board_and_direct_urls(): void
    {
        $employee = $this->actor(['view_tasks', 'view_projects']);
        $other = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $employee->id, 'role' => 'member']);
        $stage = $project->workflowStages()->where('category', 'planned')->firstOrFail();
        $assigned = Task::factory()->for($project)->create(['title' => 'Assigned task', 'is_pipeline_task' => true, 'workflow_stage_id' => $stage->id, 'assigned_to' => $employee->id]);
        $pivotAssigned = Task::factory()->for($project)->create(['title' => 'Shared task', 'is_pipeline_task' => true, 'workflow_stage_id' => $stage->id, 'assigned_to' => $other->id]);
        $pivotAssigned->assignees()->create(['user_id' => $employee->id, 'role' => 'developer', 'is_primary' => false]);
        $supervised = Task::factory()->for($project)->create(['title' => 'Supervised task', 'is_pipeline_task' => true, 'workflow_stage_id' => $stage->id, 'supervisor_id' => $employee->id]);
        $hidden = Task::factory()->for($project)->create(['title' => 'Private other task', 'is_pipeline_task' => true, 'workflow_stage_id' => $stage->id, 'assigned_to' => $other->id]);
        $actor = $this->actingAs($employee, 'sanctum');
        $rows = $actor->getJson('/api/v1/tasks/dashboard-list')->assertOk()->json('data');
        foreach ([$assigned, $pivotAssigned, $supervised] as $task) $this->assertContains($task->id, array_column($rows, 'taskId'));
        $this->assertNotContains($hidden->id, array_column($rows, 'taskId'));
        $response = $this->getJson("/api/v1/projects/{$project->id}/pipeline")->assertOk()->json('data.stages');
        $ids = collect($response)->flatMap(fn ($s) => $s['tasks'])->pluck('taskId')->all();
        $this->assertContains($assigned->id, $ids); $this->assertContains($supervised->id, $ids); $this->assertNotContains($hidden->id, $ids);
        $this->getJson("/api/v1/tasks/{$hidden->id}")->assertForbidden();
        $this->getJson("/api/v1/tasks/{$hidden->id}/activities")->assertForbidden();
        $this->getJson("/api/v1/tasks/{$assigned->id}")->assertOk();
        $all = $this->getJson('/api/v1/tasks')->assertOk()->json('data.tasks');
        $this->assertNotContains($hidden->id, array_column($all, 'taskId'));
    }

    public function test_assigned_employee_without_membership_can_access_the_project_and_task(): void
    {
        $employee = $this->actor(['view_tasks']);
        $project = Project::factory()->create();
        $stage = $project->workflowStages()->where('category', 'planned')->firstOrFail();
        $task = Task::factory()->for($project)->create(['is_pipeline_task' => true, 'workflow_stage_id' => $stage->id]);
        $task->assignees()->create(['user_id' => $employee->id, 'role' => 'developer', 'is_primary' => false]);
        $this->assertDatabaseMissing('project_members', ['project_id' => $project->id, 'user_id' => $employee->id]);
        $this->actingAs($employee, 'sanctum')->getJson("/api/v1/projects/{$project->id}")->assertOk();
        $this->getJson("/api/v1/projects/{$project->id}/pipeline")->assertOk()->assertJsonFragment(['taskId' => $task->id]);
        $this->getJson("/api/v1/tasks/{$task->id}")->assertOk();
    }

    public function test_wide_task_visibility_is_separate_from_all_project_access(): void
    {
        $viewer = $this->actor(['view_tasks', 'view_all_tasks']);
        $allProjects = $this->actor(['view_tasks', 'view_all_projects']);
        $project = Project::factory()->create(); $outside = Project::factory()->create();
        $project->members()->create(['user_id' => $viewer->id, 'role' => 'member']);
        $insideTask = Task::factory()->for($project)->create();
        $outsideTask = Task::factory()->for($outside)->create();
        $rows = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/tasks/dashboard-list')->assertOk()->json('data');
        $this->assertContains($insideTask->id, array_column($rows, 'taskId')); $this->assertNotContains($outsideTask->id, array_column($rows, 'taskId'));
        $this->getJson("/api/v1/tasks/{$outsideTask->id}")->assertForbidden();
        $rows = $this->actingAs($allProjects, 'sanctum')->getJson('/api/v1/tasks/dashboard-list')->assertOk()->json('data');
        $this->assertNotContains($insideTask->id, array_column($rows, 'taskId'));
        $this->getJson("/api/v1/tasks/{$insideTask->id}")->assertForbidden();
    }

    public function test_nested_task_details_do_not_include_unassigned_children_or_parent_titles(): void
    {
        $employee = $this->actor(['view_tasks']); $project = Project::factory()->create();
        $project->members()->create(['user_id' => $employee->id, 'role' => 'member']);
        $parent = Task::factory()->for($project)->create(['title' => 'Hidden parent']);
        $visible = Task::factory()->for($project)->create(['parent_id' => $parent->id, 'assigned_to' => $employee->id]);
        $hiddenChild = Task::factory()->for($project)->create(['parent_id' => $visible->id, 'title' => 'Hidden child']);
        $response = $this->actingAs($employee, 'sanctum')->getJson("/api/v1/tasks/{$visible->id}")->assertOk();
        $response->assertJsonPath('data.parent', null)->assertJsonMissing(['title' => 'Hidden child']);
        $this->getJson("/api/v1/tasks/{$visible->id}/children")->assertOk()->assertJsonMissing(['taskId' => $hiddenChild->id]);
    }
    public function test_time_entries_and_dependencies_do_not_reveal_hidden_task_details(): void
    {
        $employee = $this->actor(['view_tasks', 'view_all_time_entries']);
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $employee->id, 'role' => 'member']);
        $visible = Task::factory()->for($project)->create(['title' => 'My visible task', 'assigned_to' => $employee->id]);
        $hidden = Task::factory()->for($project)->create(['title' => 'Confidential task title']);
        \App\Models\TimeEntry::factory()->create(['trackable_type' => Task::class, 'trackable_id' => $visible->id]);
        \App\Models\TimeEntry::factory()->create(['trackable_type' => Task::class, 'trackable_id' => $hidden->id]);
        \App\Models\TaskDependency::create(['task_id' => $visible->id, 'depends_on_task_id' => $hidden->id, 'dependency_type' => 'finish_to_start', 'lag_days' => 0]);

        $this->actingAs($employee, 'sanctum')->getJson('/api/v1/time-entries')->assertOk()
            ->assertSee('My visible task')->assertDontSee('Confidential task title');
        $this->getJson('/api/v1/time-entries/all')->assertOk()->assertDontSee('Confidential task title');
        $this->getJson("/api/v1/tasks/{$visible->id}/dependencies")->assertOk()->assertJsonCount(0, 'data.dependencies');
    }

    public function test_archived_project_tasks_are_not_accessible_by_direct_url(): void
    {
        $employee = $this->actor(['view_tasks']);
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['assigned_to' => $employee->id]);
        $project->delete();
        $this->actingAs($employee, 'sanctum')->getJson("/api/v1/tasks/{$task->id}")->assertForbidden();
    }

    public function test_wide_visibility_respects_access_via_assignment_and_excludes_other_standalone_tasks(): void
    {
        $viewer = $this->actor(['view_all_tasks']);
        $project = Project::factory()->create();
        Task::factory()->for($project)->create(['assigned_to' => $viewer->id]);
        $otherInProject = Task::factory()->for($project)->create();
        $standalone = Task::factory()->create(['project_id' => null]);
        $rows = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/tasks/dashboard-list')->assertOk()->json('data');
        $this->assertContains($otherInProject->id, array_column($rows, 'taskId'));
        $this->assertNotContains($standalone->id, array_column($rows, 'taskId'));
        $this->getJson("/api/v1/tasks/{$standalone->id}")->assertForbidden();
    }

}
