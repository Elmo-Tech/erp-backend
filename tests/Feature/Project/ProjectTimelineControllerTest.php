<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Models\Shift;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectTimelineControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_requires_project_timeline_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects/timeline?startDate=2026-06-07&endDate=2026-06-11')
            ->assertForbidden();
    }

    public function test_timeline_allocates_task_hours_from_shift_capacity_and_reports_overload(): void
    {
        $manager = $this->userWithPermissions(['view_project_timeline', 'view_projects', 'view_all_projects']);
        $developer = User::factory()->create(['name' => 'Nour Developer']);

        $shift = Shift::create([
            'name' => 'Short Sunday Thursday',
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'working_days' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'],
            'grace_period_minutes' => 15,
        ]);

        EmployeeProfile::create([
            'user_id' => $developer->id,
            'shift_id' => $shift->id,
            'base_salary' => 0,
            'hourly_rate' => 0,
            'join_date' => '2026-01-01',
        ]);

        $project = Project::factory()->create([
            'title' => 'Business OS',
            'deadline' => '2026-06-30',
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $developer->id,
            'role' => ProjectRole::DEVELOPER,
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Build planner',
            'status' => 'in_progress',
            'estimated_hours' => 10,
            'start_date' => '2026-06-07',
            'due_date' => '2026-06-08',
            'assignee_id' => $developer->id,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/projects/timeline?startDate=2026-06-07&endDate=2026-06-11');

        $response->assertOk()
            ->assertJsonPath('data.range.startDate', '2026-06-07')
            ->assertJsonPath('data.users.0.userId', $developer->id)
            ->assertJsonPath('data.users.0.dailyCapacityHoursByDate.2026-06-07', 4)
            ->assertJsonPath('data.items.0.taskId', $task->id)
            ->assertJsonPath('data.items.0.allocatedHoursByDate.2026-06-07', 5)
            ->assertJsonPath('data.items.0.allocatedHoursByDate.2026-06-08', 5)
            ->assertJsonPath('data.conflicts.0.userId', $developer->id)
            ->assertJsonPath('data.conflicts.0.date', '2026-06-07')
            ->assertJsonPath('data.conflicts.0.overloadHours', 1)
            ->assertJsonPath('data.suggestions.0.type', 'reschedule')
            ->assertJsonPath('data.suggestions.0.taskId', $task->id);
    }

    public function test_timeline_suggests_reassigning_to_available_project_member(): void
    {
        $manager = $this->userWithPermissions(['view_project_timeline', 'view_projects', 'view_all_projects']);
        $overloaded = User::factory()->create(['name' => 'Over Loaded']);
        $available = User::factory()->create(['name' => 'Available Member']);

        $shift = Shift::create([
            'name' => 'Half Day',
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'working_days' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'],
            'grace_period_minutes' => 15,
        ]);

        EmployeeProfile::create([
            'user_id' => $overloaded->id,
            'shift_id' => $shift->id,
            'base_salary' => 0,
            'hourly_rate' => 0,
            'join_date' => '2026-01-01',
        ]);

        $project = Project::factory()->create([
            'title' => 'Urgent Portal',
            'deadline' => '2026-06-30',
        ]);

        foreach ([$overloaded, $available] as $member) {
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $member->id,
                'role' => ProjectRole::DEVELOPER,
            ]);
        }

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Urgent fix',
            'status' => 'to_do',
            'estimated_hours' => 6,
            'start_date' => '2026-06-07',
            'due_date' => '2026-06-07',
            'assignee_id' => $overloaded->id,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/projects/timeline?startDate=2026-06-07&endDate=2026-06-11');

        $response->assertOk();

        $this->assertTrue(collect($response->json('data.suggestions'))->contains(
            fn (array $suggestion) => $suggestion['type'] === 'reassign'
                && $suggestion['taskId'] === $task->id
                && $suggestion['suggested']['assigneeId'] === $available->id
        ));
    }

    public function test_timeline_excludes_projects_outside_existing_visibility_scope(): void
    {
        $viewer = $this->userWithPermissions(['view_project_timeline', 'view_projects']);
        $developer = User::factory()->create();
        $hiddenProject = Project::factory()->create(['title' => 'Hidden Project']);

        ProjectTask::create([
            'project_id' => $hiddenProject->id,
            'title' => 'Hidden task',
            'status' => 'to_do',
            'estimated_hours' => 4,
            'start_date' => '2026-06-07',
            'due_date' => '2026-06-07',
            'assignee_id' => $developer->id,
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/projects/timeline?startDate=2026-06-07&endDate=2026-06-11');

        $response->assertOk()
            ->assertJsonCount(0, 'data.items');
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
