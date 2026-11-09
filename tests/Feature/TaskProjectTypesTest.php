<?php

namespace Tests\Feature;

use App\Models\LookupOption;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskProjectTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_types_can_be_created_updated_cleared_and_are_limited_to_two(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([Permission::findOrCreate('manage_tasks', 'web'), Permission::findOrCreate('view_all_projects', 'web')]);
        $project = Project::factory()->create();
        $stage = $project->workflowStages()->where('category', 'planned')->firstOrFail();
        $types = collect(['Design', 'Social', 'Video'])->map(fn ($label) => LookupOption::create(['type' => 'project_type', 'value' => strtolower($label), 'label' => $label, 'is_active' => true]));
        $ids = $types->pluck('id')->all();
        $base = "/api/v1/projects/{$project->id}/pipeline/tasks";
        $this->actingAs($user, 'sanctum')->postJson($base, ['title' => 'Creative', 'workflowStageId' => $stage->id, 'projectTypeIds' => $ids])->assertUnprocessable();
        $response = $this->postJson($base, ['title' => 'Creative', 'workflowStageId' => $stage->id, 'projectTypeIds' => array_slice($ids, 0, 2)])->assertCreated();
        $taskId = $response->json('data.taskId');
        $response->assertJsonCount(2, 'data.projectTypes');
        $this->getJson("/api/v1/tasks/{$taskId}")->assertOk()->assertJsonCount(2, 'data.projectTypes');
        $this->getJson("/api/v1/projects/{$project->id}/pipeline")->assertOk()->assertJsonFragment(['label' => 'Design']);
        $this->patchJson("$base/$taskId", ['projectTypeIds' => [$ids[2]]])->assertOk()->assertJsonCount(1, 'data.projectTypes')->assertJsonPath('data.projectTypes.0.label', 'Video');
        $this->patchJson("$base/$taskId", ['title' => 'Renamed'])->assertOk()->assertJsonCount(1, 'data.projectTypes');
        $this->patchJson("$base/$taskId", ['projectTypeIds' => []])->assertOk()->assertJsonCount(0, 'data.projectTypes');
        $bad = LookupOption::create(['type' => 'task_status', 'value' => 'example', 'label' => 'Example']);
        $this->patchJson("$base/$taskId", ['projectTypeIds' => [$bad->id]])->assertUnprocessable();
    }

    public function test_legacy_task_types_are_saved_and_existing_project_types_are_not_copied(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([Permission::findOrCreate('manage_tasks', 'web'), Permission::findOrCreate('view_all_projects', 'web')]);
        $type = LookupOption::create(['type' => 'project_type', 'value' => 'design', 'label' => 'Design']);
        $project = Project::factory()->create(['project_type_id' => $type->id]);
        $old = Task::factory()->for($project)->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', ['projectId' => $project->id, 'title' => 'Legacy task', 'type' => 'main', 'projectTypeIds' => [$type->id]])->assertCreated()->assertJsonPath('data.projectTypes.0.id', $type->id);
        $this->getJson("/api/v1/tasks/{$old->id}")->assertOk()->assertJsonCount(0, 'data.projectTypes');
        $this->putJson("/api/v1/tasks/{$old->id}", ['projectTypeIds' => [$type->id]])->assertOk()->assertJsonPath('data.projectTypes.0.id', $type->id);
        $this->putJson("/api/v1/tasks/{$old->id}", ['projectTypeIds' => []])->assertOk()->assertJsonCount(0, 'data.projectTypes');
    }
}
