<?php

namespace Tests\Feature\HR;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Models\PayrollTransaction;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
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
            ->assertJsonPath('data.deliverableDays.0.completedTaskCount', 2)
            ->assertJsonPath('data.deliverableDays.0.tasks.0.title', 'Project QA pass')
            ->assertJsonPath('data.deliverableDays.0.tasks.0.source', 'project');
    }

    public function test_employee_report_separates_presence_from_tracked_task_time(): void
    {
        $manager = $this->userWithPermissions(['manage_employees']);
        $employee = User::factory()->create();

        Attendance::create([
            'user_id' => $employee->id,
            'date' => '2026-06-18',
            'clock_in' => '2026-06-18 09:00:00',
            'clock_out' => '2026-06-18 12:00:00',
            'status' => 'present',
            'expected_clock_in' => '09:00:00',
            'expected_clock_out' => '17:00:00',
        ]);

        $personalTask = PersonalTask::create([
            'user_id' => $employee->id,
            'title' => 'Prepare notes',
            'status' => 'done',
            'priority' => 'medium',
        ]);

        PersonalTimeEntry::create([
            'personal_task_id' => $personalTask->id,
            'user_id' => $employee->id,
            'start_time' => '2026-06-18 10:00:00',
            'end_time' => '2026-06-18 11:15:00',
            'duration_minutes' => 75,
            'is_manual' => false,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/hr/employee-report/{$employee->id}?month=6&year=2026")
            ->assertOk()
            ->assertJsonPath('data.attendanceDays.0.actualWorkedMinutes', 180)
            ->assertJsonPath('data.attendanceDays.0.trackedWorkMinutes', 75)
            ->assertJsonPath('data.attendanceDays.0.untrackedMinutes', 105)
            ->assertJsonPath('data.attendanceDays.0.expectedWorkedMinutes', 480)
            ->assertJsonPath('data.attendanceDays.0.stoppedMinutes', 105)
            ->assertJsonPath('data.attendanceDays.0.workedHours', 3);
    }

    public function test_performance_viewer_receives_operational_data_without_salary_data(): void
    {
        $viewer = $this->userWithPermissions(['view_employee_performance']);
        $employee = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'base_salary' => 8000,
            'hourly_rate' => 50,
            'join_date' => '2025-01-01',
        ]);
        PayrollTransaction::create([
            'user_id' => $employee->id,
            'amount' => 500,
            'type' => 'manual_bonus',
            'reason' => 'Sensitive bonus',
            'transaction_date' => '2026-06-15',
        ]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/hr/employee-report/{$employee->id}?month=6&year=2026")
            ->assertOk()
            ->assertJsonPath('data.summary.pointsBalance', 0)
            ->assertJsonMissingPath('data.summary.netSalary')
            ->assertJsonMissingPath('data.financialBreakdown')
            ->assertJsonMissingPath('data.manualTransactions');
    }

    public function test_salary_permission_adds_financial_report_sections(): void
    {
        $viewer = $this->userWithPermissions([
            'view_employee_performance',
            'view_employee_salary',
        ]);
        $employee = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'base_salary' => 8000,
            'hourly_rate' => 50,
            'join_date' => '2025-01-01',
        ]);
        PayrollTransaction::create([
            'user_id' => $employee->id,
            'amount' => 500,
            'type' => 'manual_bonus',
            'reason' => 'Visible bonus',
            'transaction_date' => '2026-06-15',
        ]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/hr/employee-report/{$employee->id}?month=6&year=2026")
            ->assertOk()
            ->assertJsonPath('data.summary.netSalary', 8500)
            ->assertJsonPath('data.financialBreakdown.baseSalary', 8000)
            ->assertJsonPath('data.manualTransactions.0.amount', 500);
    }

    public function test_salary_permission_alone_cannot_open_performance_report(): void
    {
        $salaryViewer = $this->userWithPermissions(['view_employee_salary']);
        $employee = User::factory()->create();

        $this->actingAs($salaryViewer, 'sanctum')
            ->getJson("/api/v1/hr/employee-report/{$employee->id}")
            ->assertForbidden();
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
