<?php

namespace Tests\Feature\Project;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MyProjectTasksControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_active_project_tasks_assigned_to_them_across_projects(): void
    {
        $user = $this->userWithPermissions(['view_projects']);
        $otherUser = User::factory()->create();

        $projectOne = Project::factory()->create(['title' => 'Website']);
        $projectTwo = Project::factory()->create(['title' => 'Portal']);

        $first = ProjectTask::create([
            'project_id' => $projectOne->id,
            'title' => 'Build checkout',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);
        $second = ProjectTask::create([
            'project_id' => $projectTwo->id,
            'title' => 'QA invoices',
            'status' => 'to_do',
            'assignee_id' => $user->id,
        ]);
        ProjectTask::create([
            'project_id' => $projectTwo->id,
            'title' => 'Done task',
            'status' => 'done',
            'assignee_id' => $user->id,
        ]);
        ProjectTask::create([
            'project_id' => $projectOne->id,
            'title' => 'Other task',
            'status' => 'in_progress',
            'assignee_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects/tasks/my-active');

        $response->assertOk()
            ->assertJsonCount(2, 'data.tasks')
            ->assertJsonPath('data.tasks.0.taskId', $first->id)
            ->assertJsonPath('data.tasks.0.project.title', 'Website')
            ->assertJsonPath('data.tasks.1.taskId', $second->id)
            ->assertJsonPath('data.tasks.1.project.title', 'Portal');
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
