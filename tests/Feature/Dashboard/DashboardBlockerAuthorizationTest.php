<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Settings\Models\User;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardBlockerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocker_reader_cannot_escalate_without_mutation_capability(): void
    {
        [$project, $task, $dependency] = $this->blockerScenario();
        $reader = $this->userWithPermissions(['view_all_blockers', 'view_all_projects']);

        $this->actingAs($reader, 'sanctum')
            ->postJson("/api/v1/dashboard/blockers/{$task->id}/escalate")
            ->assertForbidden();

        $this->assertDatabaseHas('task_dependencies', [
            'id' => $dependency->id,
            'is_escalated' => false,
            'escalated_by' => null,
        ]);
    }

    public function test_escalation_capability_cannot_bypass_project_scope(): void
    {
        [$project, $task, $dependency] = $this->blockerScenario();
        $outsider = $this->userWithPermissions([
            'view_all_blockers',
            'view_projects',
            'escalate_blockers',
        ]);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/dashboard/blockers/{$task->id}/escalate")
            ->assertForbidden();

        $this->assertDatabaseHas('task_dependencies', [
            'id' => $dependency->id,
            'is_escalated' => false,
        ]);
    }

    public function test_project_member_with_escalation_capability_can_escalate(): void
    {
        [$project, $task, $dependency] = $this->blockerScenario();
        $member = $this->projectMember($project, ['view_projects', 'escalate_blockers']);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/dashboard/blockers/{$task->id}/escalate")
            ->assertOk();

        $this->assertDatabaseHas('task_dependencies', [
            'id' => $dependency->id,
            'is_escalated' => true,
            'escalated_by' => $member->id,
        ]);
    }

    public function test_escalated_blocker_reader_cannot_resolve_without_mutation_capability(): void
    {
        [$project, $task, $dependency] = $this->blockerScenario(true);
        $reader = $this->userWithPermissions(['view_escalated_blockers', 'view_all_projects']);

        $this->actingAs($reader, 'sanctum')
            ->postJson("/api/v1/dashboard/escalated-blockers/{$dependency->id}/resolve")
            ->assertForbidden();

        $this->assertDatabaseHas('task_dependencies', [
            'id' => $dependency->id,
            'is_escalated' => true,
            'resolved_at' => null,
        ]);
    }

    public function test_resolution_capability_cannot_bypass_project_scope(): void
    {
        [$project, $task, $dependency] = $this->blockerScenario(true);
        $outsider = $this->userWithPermissions([
            'view_escalated_blockers',
            'view_projects',
            'resolve_blockers',
        ]);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/dashboard/escalated-blockers/{$dependency->id}/resolve")
            ->assertForbidden();

        $this->assertDatabaseHas('task_dependencies', [
            'id' => $dependency->id,
            'is_escalated' => true,
            'resolved_at' => null,
        ]);
    }

    public function test_project_member_with_resolution_capability_can_resolve(): void
    {
        [$project, $task, $dependency] = $this->blockerScenario(true);
        $member = $this->projectMember($project, ['view_projects', 'resolve_blockers']);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/dashboard/escalated-blockers/{$dependency->id}/resolve")
            ->assertOk();

        $this->assertDatabaseHas('task_dependencies', [
            'id' => $dependency->id,
            'is_escalated' => false,
            'resolved_by' => $member->id,
        ]);
        $this->assertNotNull($dependency->fresh()->resolved_at);
    }

    /**
     * @return array{0: Project, 1: Task, 2: TaskDependency}
     */
    private function blockerScenario(bool $escalated = false): array
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);
        $blockingTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);
        $dependency = TaskDependency::query()->create([
            'task_id' => $task->id,
            'depends_on_task_id' => $blockingTask->id,
            'dependency_type' => TaskDependency::TYPE_FINISH_TO_START,
            'is_escalated' => $escalated,
            'escalated_at' => $escalated ? now()->subHour() : null,
        ]);

        return [$project, $task, $dependency];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function projectMember(Project $project, array $permissions): User
    {
        $user = $this->userWithPermissions($permissions);
        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MANAGER->value,
        ]);

        return $user;
    }

    /**
     * @param  array<int, string>  $permissions
     */
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
