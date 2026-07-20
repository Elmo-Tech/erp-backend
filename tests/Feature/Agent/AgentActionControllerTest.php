<?php

namespace Tests\Feature\Agent;

use App\Modules\Agent\Models\AgentActionLog;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AgentActionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_actions_require_agent_permission(): void
    {
        Permission::findOrCreate('use_ai_agent', 'web');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/preview', [
                'action' => 'reports.get',
                'payload' => ['report' => 'overview'],
            ])
            ->assertForbidden();
    }

    public function test_parse_command_returns_structured_action_and_preview(): void
    {
        config(['services.openai.key' => 'test-key']);

        $user = $this->userWithPermissions(['use_ai_agent', 'view_projects', 'manage_all_projects', 'create_tasks']);
        $project = Project::factory()->create(['title' => 'Website Relaunch']);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'action' => 'project.create_tasks',
                    'payload' => [
                        'projectId' => $project->id,
                        'tasks' => [
                            [
                                'title' => 'Prepare launch QA checklist',
                                'deliverables' => ['Checklist ready'],
                            ],
                        ],
                    ],
                    'confidence' => 0.92,
                    'clarificationNeeded' => false,
                    'clarificationQuestion' => null,
                ]),
            ]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/parse', [
                'command' => 'اعمل تاسك QA checklist في مشروع Website Relaunch',
            ])
            ->assertOk()
            ->assertJsonPath('data.parsed.action', 'project.create_tasks')
            ->assertJsonPath('data.preview.executable', true)
            ->assertJsonPath('data.preview.operations.0.tasks.0.title', 'Prepare launch QA checklist');

        $this->assertDatabaseHas('agent_action_logs', [
            'user_id' => $user->id,
            'action' => 'agent.parse',
            'status' => 'parsed',
        ]);
    }

    public function test_parse_command_can_request_clarification_without_preview(): void
    {
        config(['services.openai.key' => 'test-key']);

        $user = $this->userWithPermissions(['use_ai_agent']);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'action' => 'unknown',
                    'payload' => [],
                    'confidence' => 0.2,
                    'clarificationNeeded' => true,
                    'clarificationQuestion' => 'Which project should I use?',
                ]),
            ]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/parse', [
                'command' => 'اعمل تسكات للمشروع',
            ])
            ->assertOk()
            ->assertJsonPath('data.parsed.clarificationNeeded', true)
            ->assertJsonPath('data.preview', null);
    }

    public function test_parse_command_requires_openai_key(): void
    {
        config(['services.openai.key' => null]);

        $user = $this->userWithPermissions(['use_ai_agent']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/parse', [
                'command' => 'Get overview report',
            ])
            ->assertStatus(503);
    }

    public function test_preview_and_execute_create_project_tasks_with_deliverables(): void
    {
        $user = $this->userWithPermissions(['use_ai_agent', 'view_projects', 'manage_all_projects', 'create_tasks']);
        $project = Project::factory()->create(['title' => 'Agent Project']);

        $payload = [
            'projectId' => $project->id,
            'tasks' => [
                [
                    'title' => 'Agent generated task',
                    'description' => 'Created from agent action',
                    'deliverables' => ['First deliverable', 'Second deliverable'],
                ],
            ],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/preview', [
                'action' => 'project.create_tasks',
                'payload' => $payload,
            ])
            ->assertOk()
            ->assertJsonPath('data.preview.executable', true)
            ->assertJsonPath('data.preview.operations.0.count', 1);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', [
                'action' => 'project.create_tasks',
                'payload' => $payload,
            ])
            ->assertOk()
            ->assertJsonPath('data.result.createdTasks.0.title', 'Agent generated task')
            ->assertJsonCount(2, 'data.result.createdTasks.0.deliverables');

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Agent generated task',
        ]);
        $this->assertDatabaseHas('project_task_deliverables', [
            'title' => 'First deliverable',
        ]);
        $this->assertDatabaseHas('agent_action_logs', [
            'user_id' => $user->id,
            'action' => 'project.create_tasks',
            'status' => 'executed',
        ]);
    }

    public function test_execute_start_timer_uses_task_policy_and_creates_active_entry(): void
    {
        $user = $this->userWithPermissions(['use_ai_agent', 'view_projects', 'manage_all_projects', 'start_timer']);
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Timed by agent',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', [
                'action' => 'project.start_timer',
                'payload' => [
                    'projectId' => $project->id,
                    'taskId' => $task->id,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.result.state', 'started')
            ->assertJsonPath('data.result.timeEntry.taskId', $task->id);

        $this->assertDatabaseHas('project_time_entries', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'end_time' => null,
        ]);
    }

    public function test_reports_action_requires_view_reports_and_returns_permission_scoped_data(): void
    {
        $user = $this->userWithPermissions(['use_ai_agent', 'view_reports', 'view_financial_stats']);
        Project::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', [
                'action' => 'reports.get',
                'payload' => [
                    'report' => 'overview',
                    'filters' => [],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.result.report.metrics.projectsTotal', 1)
            ->assertJsonPath('data.result.report.metrics.internalCost', null)
            ->assertJsonPath('data.result.report.metrics.profit', null);
    }

    public function test_agent_logs_are_scoped_to_current_user(): void
    {
        $user = $this->userWithPermissions(['use_ai_agent']);
        $other = $this->userWithPermissions(['use_ai_agent']);

        AgentActionLog::create([
            'user_id' => $user->id,
            'action' => 'reports.get',
            'status' => 'previewed',
            'payload' => ['report' => 'overview'],
        ]);

        AgentActionLog::create([
            'user_id' => $other->id,
            'action' => 'project.start_timer',
            'status' => 'executed',
            'payload' => ['projectId' => 1, 'taskId' => 1],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/agent/actions/logs')
            ->assertOk()
            ->assertJsonCount(1, 'data.logs')
            ->assertJsonPath('data.logs.0.action', 'reports.get');
    }

    /**
     * @param  array<int, string>  $permissions
     */
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
