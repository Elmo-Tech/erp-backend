<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\TimeEntry;
use App\Events\TimerUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GlobalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_workflow_moves_existing_tasks_and_updates_new_projects(): void
    {
        Event::fake([TimerUpdated::class]);
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('manage_workflow_templates', 'web'));
        $a = Project::factory()->create(); $b = Project::factory()->create();
        $backlog = $a->workflowStages()->where('slug', 'backlog')->firstOrFail();
        $active = $b->workflowStages()->where('slug', 'in_progress')->firstOrFail();
        $task = Task::factory()->for($a)->create(['is_pipeline_task' => true, 'workflow_stage_id' => $backlog->id, 'status' => 'todo']);
        $other = Task::factory()->for($b)->create(['is_pipeline_task' => true, 'workflow_stage_id' => $active->id, 'status' => 'in_progress']);
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/workflow-settings')->assertOk()->json('data');
        $stages = collect($response['stages'])->whereNotIn('slug', ['backlog', 'cancelled'])->values()->all();
        foreach ($stages as &$stage) if ($stage['slug'] === 'ready') $stage['name'] = 'To Do';
        unset($stage);
        $mapping = collect($response['sources'])->mapWithKeys(fn ($s) => [$s['key'] => in_array($s['slug'], ['backlog', 'cancelled']) ? 'ready' : $s['slug']])->all();
        $this->putJson('/api/v1/workflow-settings', ['revision' => $response['revision'], 'stages' => $stages, 'mapping' => $mapping])->assertOk();
        $this->assertSame('ready', $task->fresh()->workflowStage->slug);
        $this->assertSame('in_progress', $other->fresh()->workflowStage->slug);
        $this->assertSame('todo', $task->fresh()->status->value);
        $this->assertSame(5, $a->workflowStages()->count());
        $new = Project::factory()->create();
        $this->assertSame(['To Do', 'In Progress', 'On Hold', 'In Review', 'Done'], $new->workflowStages()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'global_workflow_updated']);
        $this->putJson('/api/v1/workflow-settings', ['revision' => $response['revision'], 'stages' => $stages, 'mapping' => $mapping])->assertUnprocessable();
    }

    public function test_mapping_is_required_and_permissions_are_enforced(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/workflow-settings')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('manage_workflow_templates', 'web'));
        Project::factory()->create();
        $snapshot = $this->getJson('/api/v1/workflow-settings')->assertOk()->json('data');
        $this->putJson('/api/v1/workflow-settings', ['revision' => $snapshot['revision'], 'stages' => $snapshot['stages'], 'mapping' => []])->assertUnprocessable();
    }
    public function test_mapping_to_done_completes_tasks_and_stops_active_timers(): void
    {
        Event::fake([TimerUpdated::class]);
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('manage_workflow_templates', 'web'));
        $project = Project::factory()->create();
        $source = $project->workflowStages()->where('slug', 'in_progress')->firstOrFail();
        $task = Task::factory()->for($project)->create(['is_pipeline_task' => true, 'workflow_stage_id' => $source->id, 'status' => 'in_progress']);
        $entry = TimeEntry::factory()->active()->create(['user_id' => $admin->id, 'trackable_id' => $task->id, 'trackable_type' => Task::class, 'start_time' => now()->subHour()]);
        $snapshot = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/workflow-settings')->assertOk()->json('data');
        $mapping = collect($snapshot['sources'])->mapWithKeys(fn ($s) => [$s['key'] => $s['slug'] === 'in_progress' ? 'done' : $s['slug']])->all();
        $this->putJson('/api/v1/workflow-settings', ['revision' => $snapshot['revision'], 'stages' => $snapshot['stages'], 'mapping' => $mapping])->assertOk();
        $this->assertSame('completed', $task->fresh()->status->value);
        $this->assertSame('done', $task->fresh()->workflowStage->slug);
        $this->assertNotNull($entry->fresh()->end_time);
    }

    public function test_old_per_project_workflow_edit_is_no_longer_available(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([Permission::findOrCreate('manage_project_workflows', 'web'), Permission::findOrCreate('view_all_projects', 'web')]);
        $project = Project::factory()->create();
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/projects/{$project->id}/pipeline/stages", [])->assertForbidden();
        $this->postJson("/api/v1/workflow-templates", ["isDefault" => true])->assertForbidden();
    }

    public function test_dashboard_uses_global_stage_for_legacy_tasks(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([Permission::findOrCreate('view_tasks', 'web'), Permission::findOrCreate('view_all_tasks', 'web'), Permission::findOrCreate('view_all_projects', 'web')]);
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['is_pipeline_task' => false, 'workflow_stage_id' => null, 'status' => 'completed']);
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks/dashboard-list')->assertOk()->json('data');
        $row = collect($response)->firstWhere('taskId', $task->id);
        $this->assertSame('Done', $row['status']);
        $this->assertSame(5, $row['statusPosition']);
    }

}
