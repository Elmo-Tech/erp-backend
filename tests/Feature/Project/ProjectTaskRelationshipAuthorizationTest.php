<?php

namespace Tests\Feature\Project;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectRole;
use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectTaskRelationshipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_task_create_rejects_a_foreign_milestone(): void
    {
        [$actor, $project] = $this->projectActor(['create_tasks']);
        $foreignMilestone = $this->milestoneFor(Project::factory()->create());

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Foreign milestone task',
                'milestoneId' => $foreignMilestone->id,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('project_tasks', 0);
    }

    public function test_direct_task_create_requires_assignment_capability(): void
    {
        [$actor, $project] = $this->projectActor(['create_tasks']);
        $member = $this->addMember($project);

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Unauthorized assignment',
                'assigneeId' => $member->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('project_tasks', 0);
    }

    public function test_direct_task_create_rejects_a_nonmember_assignee(): void
    {
        [$actor, $project] = $this->projectActor(['create_tasks', 'assign_tasks']);
        $nonmember = User::factory()->create();

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Nonmember assignment',
                'assigneeId' => $nonmember->id,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('project_tasks', 0);
    }

    public function test_direct_task_create_accepts_project_scoped_relationships(): void
    {
        [$actor, $project] = $this->projectActor(['create_tasks', 'assign_tasks']);
        $member = $this->addMember($project);
        $milestone = $this->milestoneFor($project);

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Scoped task',
                'assigneeId' => $member->id,
                'milestoneId' => $milestone->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'assignee_id' => $member->id,
            'milestone_id' => $milestone->id,
        ]);
    }

    public function test_direct_task_update_rejects_foreign_relationships_without_mutation(): void
    {
        [$actor, $project] = $this->projectActor(['edit_tasks', 'assign_tasks']);
        $task = $this->taskFor($project);
        $foreignProject = Project::factory()->create();
        $foreignMilestone = $this->milestoneFor($foreignProject);
        $nonmember = User::factory()->create();

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
                'milestoneId' => $foreignMilestone->id,
            ])
            ->assertUnprocessable();

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
                'assigneeId' => $nonmember->id,
            ])
            ->assertUnprocessable();

        $task->refresh();
        $this->assertNull($task->milestone_id);
        $this->assertNull($task->assignee_id);
    }

    public function test_agent_batch_preflights_all_milestones_before_writing(): void
    {
        [$actor, $project] = $this->projectActor(['use_ai_agent', 'create_tasks']);
        $ownMilestone = $this->milestoneFor($project);
        $foreignMilestone = $this->milestoneFor(Project::factory()->create());

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', [
                'action' => 'project.create_tasks',
                'payload' => [
                    'projectId' => $project->id,
                    'tasks' => [
                        ['title' => 'Valid first task', 'milestoneId' => $ownMilestone->id],
                        ['title' => 'Invalid second task', 'milestoneId' => $foreignMilestone->id],
                    ],
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('project_tasks', 0);
    }

    public function test_agent_assignment_requires_capability_and_project_membership(): void
    {
        [$actor, $project] = $this->projectActor(['use_ai_agent', 'create_tasks']);
        $member = $this->addMember($project);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/agent/actions/preview', $this->agentTaskAction($project, $member))
            ->assertForbidden();

        $actor->givePermissionTo(Permission::findOrCreate('assign_tasks', 'web'));
        $nonmember = User::factory()->create();

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', $this->agentTaskAction($project, $nonmember))
            ->assertUnprocessable();

        $this->assertDatabaseCount('project_tasks', 0);
    }

    public function test_agent_accepts_same_project_member_and_milestone(): void
    {
        [$actor, $project] = $this->projectActor(['use_ai_agent', 'create_tasks', 'assign_tasks']);
        $member = $this->addMember($project);
        $milestone = $this->milestoneFor($project);

        $action = $this->agentTaskAction($project, $member, $milestone);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/agent/actions/execute', $action)
            ->assertOk();

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'assignee_id' => $member->id,
            'milestone_id' => $milestone->id,
        ]);
    }

    private function projectActor(array $permissions): array
    {
        $actor = $this->userWithPermissions($permissions);
        $project = Project::factory()->create();
        $this->addMember($project, $actor);

        return [$actor, $project];
    }

    private function addMember(Project $project, ?User $user = null): User
    {
        $user ??= User::factory()->create();

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        return $user;
    }

    private function milestoneFor(Project $project): Milestone
    {
        return Milestone::create([
            'project_id' => $project->id,
            'title' => "Milestone {$project->id}",
            'status' => MilestoneStatus::ACTIVE,
            'progress_percentage' => 0,
            'due_date' => now()->addWeek(),
        ]);
    }

    private function taskFor(Project $project): ProjectTask
    {
        return ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Existing scoped task',
            'status' => 'to_do',
        ]);
    }

    private function agentTaskAction(Project $project, User $assignee, ?Milestone $milestone = null): array
    {
        return [
            'action' => 'project.create_tasks',
            'payload' => [
                'projectId' => $project->id,
                'tasks' => [[
                    'title' => 'Agent relationship task',
                    'assigneeId' => $assignee->id,
                    'milestoneId' => $milestone?->id,
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
