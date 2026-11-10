<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskChecklistAndSupervisorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_supervisors_can_view_and_run_supervision_and_active_session_blocks_removal(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo([Permission::findOrCreate('manage_tasks', 'web'), Permission::findOrCreate('manage_task_team', 'web'), Permission::findOrCreate('view_all_projects', 'web')]);
        $first = User::factory()->create(['is_employee' => true, 'is_active' => true]);
        $second = User::factory()->create(['is_employee' => true, 'is_active' => true]);
        $second->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));
        $project = Project::factory()->create();
        $stage = $project->workflowStages()->where('category', 'planned')->firstOrFail();
        $task = $this->actingAs($manager, 'sanctum')->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", ['title' => 'With supervisors', 'workflowStageId' => $stage->id, 'supervisorIds' => [$first->id, $second->id]])->assertCreated()->assertJsonCount(2, 'data.supervisors')->json('data.taskId');
        $this->getJson("/api/v1/tasks/$task")->assertOk()->assertJsonCount(2, 'data.supervisors');
        $this->actingAs($second, 'sanctum')->getJson("/api/v1/tasks/$task")->assertOk();
        $this->getJson("/api/v1/tasks/$task/supervision")->assertOk()->assertJsonCount(2, 'data.supervisors');
        $this->postJson("/api/v1/tasks/$task/supervision-sessions/start", [])->assertCreated();
        $this->actingAs($manager, 'sanctum')->putJson("/api/v1/tasks/$task/supervisor", ['supervisorIds' => [$first->id]])->assertUnprocessable();
        $this->assertDatabaseHas('tasks', ['id' => $task, 'secondary_supervisor_id' => $second->id]);
        $this->putJson("/api/v1/tasks/$task/supervisor", ['supervisorIds' => [$first->id, $first->id]])->assertUnprocessable();
    }

    public function test_checklist_is_saved_and_only_authorized_people_can_toggle_it(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo([Permission::findOrCreate('manage_tasks', 'web'), Permission::findOrCreate('view_all_projects', 'web')]);
        $employee = User::factory()->create(['is_employee' => true, 'is_active' => true]);
        $employee->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));
        $outsider = User::factory()->create();
        $outsider->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $employee->id, 'role' => 'member']);
        $project->members()->create(['user_id' => $outsider->id, 'role' => 'member']);
        $id = (string) Str::uuid();
        $stage = $project->workflowStages()->where('category', 'planned')->firstOrFail();
        $task = $this->actingAs($manager, 'sanctum')->postJson("/api/v1/projects/{$project->id}/pipeline/tasks", ['title' => 'Checklist', 'description' => 'Keep this text', 'workflowStageId' => $stage->id, 'assignedUserIds' => [$employee->id], 'checklist' => [['id' => $id, 'text' => 'First step', 'completed' => false]]])->assertCreated()->assertJsonPath('data.checklist.0.completed', false)->json('data.taskId');
        $this->actingAs($outsider, 'sanctum')->patchJson("/api/v1/tasks/$task/checklist/$id", ['completed' => true])->assertForbidden();
        $this->actingAs($employee, 'sanctum')->patchJson("/api/v1/tasks/$task/checklist/$id", ['completed' => true])->assertOk();
        $this->getJson("/api/v1/tasks/$task")->assertOk()->assertJsonPath('data.checklist.0.completed', true)->assertJsonPath('data.description', 'Keep this text');
        $this->patchJson("/api/v1/tasks/$task/checklist/".Str::uuid(), ['completed' => true])->assertNotFound();
    }
}
