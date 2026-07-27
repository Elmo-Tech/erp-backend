<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectPipelineWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_pipeline_exposes_the_default_delivery_workflow(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'manage_all_projects', 'view_tasks']);
        $project = Project::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/workflow")
            ->assertOk()
            ->assertJsonCount(8, 'data.stages')
            ->assertJsonPath('data.stages.0.name', 'Backlog')
            ->assertJsonPath('data.stages.3.status', 'on_hold')
            ->assertJsonPath('data.stages.5.status', 'qa')
            ->assertJsonPath('data.stages.6.isTerminal', true);
    }

    public function test_multi_assignee_creation_adds_active_employees_to_the_project(): void
    {
        $actor = $this->userWithPermissions([
            'view_projects',
            'manage_all_projects',
            'view_tasks',
            'manage_tasks',
            'assign_tasks',
        ]);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $project = Project::factory()->create();

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Shared delivery task',
                'assigneeIds' => [$first->id, $second->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.assigneeId', $first->id)
            ->assertJsonPath('data.assigneeIds', [$first->id, $second->id])
            ->assertJsonCount(2, 'data.assignees');

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $first->id,
        ]);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $second->id,
        ]);
        $this->assertDatabaseCount('project_task_assignees', 2);
    }

    public function test_collaborator_can_see_and_run_their_assigned_task(): void
    {
        $actor = $this->userWithPermissions([
            'view_projects',
            'manage_all_projects',
            'view_tasks',
            'manage_tasks',
            'assign_tasks',
        ]);
        $collaborator = $this->userWithPermissions([
            'view_projects',
            'view_tasks',
            'work_on_tasks',
            'start_timer',
            'stop_timer',
        ]);
        $project = Project::factory()->create();

        $taskId = $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Collaborative implementation',
                'assigneeIds' => [$actor->id, $collaborator->id],
            ])
            ->json('data.taskId');

        $this->actingAs($collaborator, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/tasks")
            ->assertOk()
            ->assertJsonPath('data.tasks.0.taskId', $taskId);

        $this->actingAs($collaborator, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$taskId}/time-entries/lifecycle", [
                'action' => 'start',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'start');
    }

    public function test_moving_a_running_task_to_hold_closes_the_timer(): void
    {
        $worker = $this->userWithPermissions([
            'view_projects',
            'view_tasks',
            'work_on_tasks',
            'change_task_status',
            'start_timer',
            'stop_timer',
        ]);
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $worker->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $workflow = $this->actingAs($worker, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/workflow")
            ->assertOk()
            ->json('data.stages');
        $readyStage = collect($workflow)->firstWhere('status', 'to_do');
        $holdStage = collect($workflow)->firstWhere('status', 'on_hold');
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Pause safely',
            'status' => 'to_do',
            'workflow_stage_id' => $readyStage['stageId'],
            'assignee_id' => $worker->id,
        ]);
        $task->assignees()->attach($worker->id);

        $this->actingAs($worker, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/lifecycle", [
                'action' => 'start',
            ])
            ->assertOk();

        $this->actingAs($worker, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/move", [
                'workflowStageId' => $holdStage['stageId'],
                'position' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'on_hold');

        $this->assertSame(0, ProjectTimeEntry::whereNull('end_time')->count());
    }

    public function test_hold_authorization_failure_does_not_stop_the_running_timer(): void
    {
        $worker = $this->userWithPermissions([
            'view_projects',
            'view_tasks',
            'start_timer',
            'stop_timer',
        ]);
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $worker->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Protected running task',
            'status' => 'to_do',
            'assignee_id' => $worker->id,
        ]);
        $task->assignees()->attach($worker->id);

        $this->actingAs($worker, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/lifecycle", [
                'action' => 'start',
            ])
            ->assertOk();

        $this->actingAs($worker, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/lifecycle", [
                'action' => 'hold',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('project_time_entries', [
            'task_id' => $task->id,
            'user_id' => $worker->id,
            'end_time' => null,
        ]);
    }

    public function test_task_cover_upload_is_returned_on_the_pipeline_card_contract(): void
    {
        Storage::fake('public');
        $actor = $this->userWithPermissions([
            'view_projects',
            'manage_all_projects',
            'view_tasks',
            'edit_tasks',
        ]);
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Visual task',
            'status' => 'to_do',
        ]);

        $this->actingAs($actor, 'sanctum')
            ->post("/api/v1/projects/{$project->id}/tasks/{$task->id}/cover", [
                'coverImage' => UploadedFile::fake()->image('cover.webp', 1200, 600),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.taskId', $task->id)
            ->assertJsonPath('data.coverImageUrl', fn ($url): bool => str_contains($url, 'project-task-covers/'));

        Storage::disk('public')->assertExists($task->fresh()->cover_image_path);
    }

    public function test_reusable_template_can_replace_and_reorder_a_project_workflow(): void
    {
        $actor = $this->userWithPermissions([
            'view_projects',
            'manage_all_projects',
            'view_tasks',
            'manage_project_workflows',
            'manage_workflow_templates',
        ]);
        $project = Project::factory()->create();
        $stages = $this->actingAs($actor, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/workflow")
            ->assertOk()
            ->json('data.stages');
        $templateStages = collect($stages)->reverse()->values()->map(
            fn (array $stage, int $position): array => [
                'name' => $position === 0 ? 'Archived outcomes' : $stage['name'],
                'status' => $stage['status'],
                'color' => $stage['color'],
                'isTerminal' => $stage['isTerminal'],
            ]
        )->all();

        $templateId = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/projects/workflow-templates', [
                'name' => 'Reverse delivery',
                'stages' => $templateStages,
            ])
            ->assertCreated()
            ->json('data.templateId');

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/workflow/apply-template", [
                'templateId' => $templateId,
            ])
            ->assertOk()
            ->assertJsonPath('data.stages.0.name', 'Archived outcomes')
            ->assertJsonPath('data.stages.0.position', 0);
    }

    public function test_changing_a_stage_system_state_keeps_its_tasks_consistent(): void
    {
        $actor = $this->userWithPermissions([
            'view_projects',
            'manage_all_projects',
            'view_tasks',
            'manage_project_workflows',
        ]);
        $project = Project::factory()->create();
        $stages = $this->actingAs($actor, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/workflow")
            ->assertOk()
            ->json('data.stages');
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'State-bound pipeline task',
            'status' => 'backlog',
            'workflow_stage_id' => $stages[0]['stageId'],
        ]);
        [$stages[0]['status'], $stages[5]['status']] = [$stages[5]['status'], $stages[0]['status']];
        $payload = collect($stages)->map(fn (array $stage): array => [
            'id' => $stage['stageId'],
            'name' => $stage['name'],
            'status' => $stage['status'],
            'color' => $stage['color'],
            'isTerminal' => $stage['isTerminal'],
        ])->all();

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/projects/{$project->id}/workflow", ['stages' => $payload])
            ->assertOk();

        $this->assertSame('qa', $task->refresh()->status->value);
    }

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
