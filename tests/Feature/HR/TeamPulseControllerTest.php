<?php

namespace Tests\Feature\HR;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TeamPulseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_managers_can_view_clocked_in_users_with_active_tasks(): void
    {
        Carbon::setTestNow('2026-05-13 10:30:00');

        $manager = $this->userWithPermissions(['view_team_pulse']);
        $workingUser = User::factory()->create(['name' => 'Ahmed', 'is_employee' => true]);
        $idleUser = User::factory()->create(['name' => 'Sara', 'is_employee' => true]);
        $clockedOutUser = User::factory()->create(['name' => 'Omar', 'is_employee' => true]);

        Attendance::create([
            'user_id' => $workingUser->id,
            'date' => '2026-05-13',
            'clock_in' => '2026-05-13 09:00:00',
            'status' => 'present',
        ]);
        Attendance::create([
            'user_id' => $idleUser->id,
            'date' => '2026-05-13',
            'clock_in' => '2026-05-13 09:10:00',
            'status' => 'present',
        ]);
        Attendance::create([
            'user_id' => $clockedOutUser->id,
            'date' => '2026-05-13',
            'clock_in' => '2026-05-13 09:10:00',
            'clock_out' => '2026-05-13 10:00:00',
            'status' => 'present',
        ]);

        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'API Auth Task',
            'status' => 'in_progress',
            'assignee_id' => $workingUser->id,
        ]);
        ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $workingUser->id,
            'start_time' => '2026-05-13 09:07:00',
            'is_manual' => false,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/hr/team-pulse');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.user.name', 'Ahmed')
            ->assertJsonPath('data.0.status', 'clocked_in')
            ->assertJsonPath('data.0.activeTaskId', $task->id)
            ->assertJsonPath('data.0.activeTaskTitle', 'API Auth Task')
            ->assertJsonPath('data.0.elapsedSeconds', 4980)
            ->assertJsonPath('data.1.user.name', 'Sara')
            ->assertJsonPath('data.1.status', 'clocked_in')
            ->assertJsonPath('data.1.activeTaskId', null)
            ->assertJsonPath('data.2.user.name', 'Omar')
            ->assertJsonPath('data.2.status', 'idle')
            ->assertJsonPath('data.2.activeTaskId', null);

        Carbon::setTestNow();
    }

    public function test_team_pulse_shows_active_personal_task_timers(): void
    {
        Carbon::setTestNow('2026-06-16 10:30:00');

        $manager = $this->userWithPermissions(['view_team_pulse']);
        $workingUser = User::factory()->create(['name' => 'Nour', 'is_employee' => true]);

        Attendance::create([
            'user_id' => $workingUser->id,
            'date' => '2026-06-16',
            'clock_in' => '2026-06-16 09:00:00',
            'status' => 'present',
        ]);

        $task = PersonalTask::create([
            'user_id' => $workingUser->id,
            'title' => 'Write handover notes',
            'status' => 'in_progress',
            'due_date' => '2026-06-16',
            'priority' => 'medium',
        ]);

        PersonalTimeEntry::create([
            'personal_task_id' => $task->id,
            'user_id' => $workingUser->id,
            'start_time' => '2026-06-16 10:00:00',
            'is_manual' => false,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/hr/team-pulse');

        $response->assertOk()
            ->assertJsonPath('data.0.user.name', 'Nour')
            ->assertJsonPath('data.0.status', 'clocked_in')
            ->assertJsonPath('data.0.activeTaskId', $task->id)
            ->assertJsonPath('data.0.activeTaskTitle', 'Write handover notes')
            ->assertJsonPath('data.0.activeProjectId', null)
            ->assertJsonPath('data.0.activeProjectTitle', 'Personal')
            ->assertJsonPath('data.0.elapsedSeconds', 1800);

        Carbon::setTestNow();
    }

    public function test_team_timer_managers_cannot_view_company_team_pulse(): void
    {
        $manager = $this->userWithPermissions(['manage_team_timers']);
        User::factory()->create(['name' => 'Nour', 'is_employee' => true]);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/hr/team-pulse')
            ->assertForbidden();
    }

    public function test_non_managers_cannot_view_team_pulse(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/hr/team-pulse')
            ->assertForbidden();
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
