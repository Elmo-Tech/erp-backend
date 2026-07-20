<?php

namespace Tests\Feature\Agent;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AgentActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_timer_preview_requires_canonical_timer_permission(): void
    {
        [$user, $project, $task] = $this->assignedTaskFixture();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/preview', $this->timerAction($project, $task))
            ->assertForbidden();

        $this->assertDatabaseCount('project_time_entries', 0);
        $this->assertSame('to_do', $task->fresh()->status->value);
    }

    public function test_agent_timer_execution_requires_canonical_timer_permission(): void
    {
        [$user, $project, $task] = $this->assignedTaskFixture();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', $this->timerAction($project, $task))
            ->assertForbidden();

        $this->assertDatabaseCount('project_time_entries', 0);
        $this->assertSame('to_do', $task->fresh()->status->value);
    }

    public function test_agent_timer_accepts_exact_timer_permission(): void
    {
        [$user, $project, $task] = $this->assignedTaskFixture(['start_timer']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', $this->timerAction($project, $task))
            ->assertOk()
            ->assertJsonPath('data.result.state', 'started');

        $this->assertDatabaseHas('project_time_entries', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'end_time' => null,
        ]);
    }

    public function test_agent_deliverable_preview_requires_canonical_create_permission(): void
    {
        [$user, $project] = $this->taskCreatorFixture();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/preview', $this->createTasksAction($project, ['Acceptance checklist']))
            ->assertForbidden();

        $this->assertDatabaseCount('project_tasks', 0);
        $this->assertDatabaseCount('project_task_deliverables', 0);
    }

    public function test_agent_deliverable_execution_requires_canonical_create_permission(): void
    {
        [$user, $project] = $this->taskCreatorFixture();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', $this->createTasksAction($project, ['Acceptance checklist']))
            ->assertForbidden();

        $this->assertDatabaseCount('project_tasks', 0);
        $this->assertDatabaseCount('project_task_deliverables', 0);
    }

    public function test_agent_task_without_deliverables_preserves_task_creation(): void
    {
        [$user, $project] = $this->taskCreatorFixture();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', $this->createTasksAction($project))
            ->assertOk();

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Agent scoped task',
        ]);
        $this->assertDatabaseCount('project_task_deliverables', 0);
    }

    public function test_agent_deliverable_accepts_exact_create_permission(): void
    {
        [$user, $project] = $this->taskCreatorFixture(['create_project_task_deliverables']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', $this->createTasksAction($project, ['Acceptance checklist']))
            ->assertOk();

        $this->assertDatabaseHas('project_task_deliverables', [
            'title' => 'Acceptance checklist',
        ]);
    }

    public function test_agent_financial_report_requires_financial_stats_permission(): void
    {
        $user = $this->userWithPermissions(['use_ai_agent', 'view_reports']);
        $payload = [
            'action' => 'reports.get',
            'payload' => ['report' => 'overview', 'filters' => []],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/preview', $payload)
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', $payload)
            ->assertForbidden();
    }

    private function assignedTaskFixture(array $extraPermissions = []): array
    {
        $user = $this->userWithPermissions(array_merge(['use_ai_agent'], $extraPermissions));
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Agent timer boundary',
            'status' => 'to_do',
            'assignee_id' => $user->id,
        ]);

        return [$user, $project, $task];
    }

    private function taskCreatorFixture(array $extraPermissions = []): array
    {
        $user = $this->userWithPermissions(array_merge([
            'use_ai_agent',
            'create_tasks',
        ], $extraPermissions));
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        return [$user, $project];
    }

    private function timerAction(Project $project, ProjectTask $task): array
    {
        return [
            'action' => 'project.start_timer',
            'payload' => [
                'projectId' => $project->id,
                'taskId' => $task->id,
            ],
        ];
    }

    private function createTasksAction(Project $project, array $deliverables = []): array
    {
        return [
            'action' => 'project.create_tasks',
            'payload' => [
                'projectId' => $project->id,
                'tasks' => [[
                    'title' => 'Agent scoped task',
                    'deliverables' => $deliverables,
                ]],
            ],
        ];
    }

    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
