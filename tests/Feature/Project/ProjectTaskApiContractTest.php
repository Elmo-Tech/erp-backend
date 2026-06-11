<?php

namespace Tests\Feature\Project;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectTaskApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_task_index_returns_flat_contract_without_legacy_subtask_fields(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'manage_all_projects', 'view_tasks']);
        $project = Project::factory()->create();

        collect(range(1, 3))->each(function (int $index) use ($project, $user): void {
            $task = ProjectTask::create([
                'project_id' => $project->id,
                'title' => "Execution task {$index}",
                'status' => 'to_do',
                'assignee_id' => $user->id,
            ]);

            ProjectTimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'start_time' => now()->subHours(2),
                'end_time' => now()->subHour(),
                'duration_minutes' => 60 + $index,
                'is_manual' => true,
            ]);
        });

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/tasks");

        $queryCount = count(DB::getQueryLog());

        $response->assertOk()
            ->assertJsonPath('data.tasks.0.title', 'Execution task 1')
            ->assertJsonPath('data.tasks.0.totalLoggedMinutes', 61);

        $this->assertSame([
            'taskId',
            'projectId',
            'milestoneId',
            'title',
            'description',
            'status',
            'statusLabel',
            'estimatedHours',
            'startDate',
            'dueDate',
            'assigneeId',
            'isInBacklog',
            'totalLoggedMinutes',
            'assignee',
            'milestone',
            'deliverables',
            'timeEntries',
            'createdAt',
            'updatedAt',
        ], array_keys($response->json('data.tasks.0')));

        $this->assertLessThanOrEqual(8, $queryCount);
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
