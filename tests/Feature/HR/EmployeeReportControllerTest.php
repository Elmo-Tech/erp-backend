<?php

namespace Tests\Feature\HR;

use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_report_counts_project_and_personal_tasks(): void
    {
        $manager = $this->userWithPermissions(['manage_employees']);
        $employee = User::factory()->create();
        $project = Project::factory()->create();

        ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Project QA pass',
            'status' => ExecutionTaskStatus::DONE,
            'assignee_id' => $employee->id,
            'estimated_hours' => 2.5,
            'due_date' => '2026-06-10',
        ]);

        PersonalTask::create([
            'user_id' => $employee->id,
            'title' => 'Personal admin',
            'status' => 'done',
            'estimated_minutes' => 30,
            'due_date' => '2026-06-10',
            'completed_at' => '2026-06-10 12:00:00',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/hr/employee-report/{$employee->id}?month=6&year=2026");

        $response->assertOk()
            ->assertJsonPath('data.summary.totalTaskHours', 3)
            ->assertJsonPath('data.summary.completedTaskCount', 2)
            ->assertJsonPath('data.deliverableDays.0.date', '2026-06-10')
            ->assertJsonPath('data.deliverableDays.0.taskCount', 2)
            ->assertJsonPath('data.deliverableDays.0.completedTaskCount', 2);
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
