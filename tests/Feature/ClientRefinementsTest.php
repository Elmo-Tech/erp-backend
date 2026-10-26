<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Events\TimerUpdated;
use App\Jobs\DeleteProjectUploads;
use App\Jobs\SendWebPush;
use App\Models\Device;
use App\Models\LeaveType;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Sales\TimeTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientRefinementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_disconnected_work_timer_stays_active_and_server_reminders_repeat_until_stopped(): void
    {
        Event::fake([NotificationCreated::class, TimerUpdated::class]);
        Bus::fake([SendWebPush::class]);
        $user = User::factory()->create(['is_active' => true]);
        $user->forceFill(['timer_reminder_minutes' => 5])->save();
        $task = Task::factory()->create();
        $entry = TimeEntry::factory()->create([
            'user_id' => $user->id, 'trackable_type' => Task::class, 'trackable_id' => $task->id,
            'start_time' => now()->subMinutes(6), 'end_time' => null, 'last_heartbeat_at' => now()->subMinutes(6),
        ]);
        $this->assertSame($entry->id, app(TimeTrackingService::class)->getActiveTimer($user)->id);
        $this->artisan('time-entries:remind')->assertSuccessful();
        $this->artisan('time-entries:remind')->assertSuccessful();
        Bus::assertDispatchedTimes(SendWebPush::class, 1);
        $this->travel(5)->minutes();
        $this->artisan('time-entries:remind')->assertSuccessful();
        Bus::assertDispatchedTimes(SendWebPush::class, 2);
        $entry->update(['end_time' => now()]);
        $this->travel(10)->minutes();
        $this->artisan('time-entries:remind')->assertSuccessful();
        Bus::assertDispatchedTimes(SendWebPush::class, 2);
    }

    public function test_profile_is_self_readable_and_not_exposed_to_unprivileged_colleagues(): void
    {
        $employee = User::factory()->create(['national_id' => '12345678901234', 'mobile' => '+201012345678', 'job_title' => 'Designer', 'work_arrangement' => 'remote']);
        $other = User::factory()->create();
        $this->actingAs($employee, 'sanctum')->getJson('/api/v1/employees/'.$employee->id)->assertOk()
            ->assertJsonPath('data.nationalId', '12345678901234')->assertJsonPath('data.workArrangement', 'remote');
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/employees/'.$employee->id)->assertForbidden();
        $this->getJson('/api/v1/employees')->assertForbidden();
        $employee->givePermissionTo(Permission::findOrCreate('manage_protected_users', 'web'));
        $other->givePermissionTo([Permission::findOrCreate('view_employees', 'web'), Permission::findOrCreate('view_employee_salary', 'web')]);
        $this->getJson('/api/v1/employees/'.$employee->id)->assertForbidden();
        $row = collect($this->getJson('/api/v1/employees')->assertOk()->json('data.employees'))->firstWhere('userId', $employee->id);
        $this->assertNull($row['nationalId']);
        $this->assertNull($row['salary']);
    }

    public function test_action_permissions_allow_leave_creation_and_review_independently(): void
    {
        Notification::fake();
        $creator = User::factory()->create();
        $reviewer = User::factory()->create();
        $employee = User::factory()->create(['national_id' => '12345678901234']);
        $creator->givePermissionTo(Permission::findOrCreate('create_leaves', 'web'));
        $reviewer->givePermissionTo(Permission::findOrCreate('approve_leaves', 'web'));
        $leave = $this->actingAs($creator, 'sanctum')->postJson('/api/v1/employees/'.$employee->id.'/leaves', [
            'leaveTypeId' => LeaveType::where('slug', 'annual')->value('id'),
            'startDate' => '2026-10-26', 'endDate' => '2026-10-26',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->getJson('/api/v1/employees/'.$employee->id.'/leaves?year=2026')->assertOk();
        $this->getJson('/api/v1/employees/'.$employee->id)->assertOk()
            ->assertJsonPath('data.canViewPersonalDetails', false)->assertJsonPath('data.nationalId', null);
        $this->postJson('/api/v1/employee-leaves/'.$leave.'/approve')->assertForbidden();
        $this->getJson('/api/v1/employees/'.$employee->id.'/employment-contracts')->assertForbidden();
        $this->actingAs($reviewer, 'sanctum')->postJson('/api/v1/employee-leaves/'.$leave.'/approve')->assertOk();
        $this->postJson('/api/v1/employees/'.$employee->id.'/leaves', [
            'leaveTypeId' => LeaveType::where('slug', 'annual')->value('id'),
            'startDate' => '2026-10-27', 'endDate' => '2026-10-27',
        ])->assertForbidden();
    }

    public function test_project_trash_restore_and_permanent_delete_are_authorized_and_cascade_tasks_and_timers(): void
    {
        Bus::fake([DeleteProjectUploads::class]);
        Event::fake([TimerUpdated::class]);
        $actor = User::factory()->create();
        foreach (['view_all_projects', 'manage_projects', 'view_deleted_projects', 'restore_projects', 'force_delete_projects'] as $permission) {
            $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $project = Project::factory()->create(['title' => 'Project to remove']);
        $other = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $entry = TimeEntry::factory()->create(['trackable_type' => Task::class, 'trackable_id' => $task->id, 'end_time' => now()]);
        $this->actingAs($actor, 'sanctum')->deleteJson('/api/v1/projects/'.$project->id)->assertOk();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->getJson('/api/v1/projects/trash')->assertOk()->assertJsonPath('data.0.projectId', $project->id);
        $this->postJson('/api/v1/projects/'.$project->id.'/restore')->assertOk();
        $this->assertNotSoftDeleted('projects', ['id' => $project->id]);
        $this->deleteJson('/api/v1/projects/'.$project->id, ['permanent' => true, 'confirmation' => 'wrong'])->assertUnprocessable();
        $this->deleteJson('/api/v1/projects/'.$project->id, ['permanent' => true, 'confirmation' => $project->title])->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('projects', ['id' => $other->id]);
    }

    public function test_push_preferences_are_personal_and_only_allow_fixed_intervals(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/notifications/preferences', ['reminderMinutes' => 7])->assertUnprocessable();
        $this->putJson('/api/v1/notifications/preferences', ['reminderMinutes' => 10])->assertOk();
        $this->getJson('/api/v1/notifications/settings')->assertOk()->assertJsonPath('data.reminderMinutes', 10);
        $this->assertSame(15, $other->fresh()->timer_reminder_minutes);
        $this->postJson('/api/v1/notifications/push-subscriptions', ['endpoint' => 'https://127.0.0.1/push', 'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('b', 22)]])->assertUnprocessable();
    }

    public function test_dashboard_working_status_comes_from_timers_and_employee_project_deadlines_are_scoped(): void
    {
        Event::fake([TimerUpdated::class]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        foreach (['view_dashboard', 'view_all_projects'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $employee = User::factory()->create(['is_employee' => true, 'is_active' => true]);
        $employee->givePermissionTo(Permission::findOrCreate('view_dashboard', 'web'));
        $project = Project::factory()->create(['deadline' => '2026-11-01']);
        $other = Project::factory()->create();
        $project->members()->create(['user_id' => $employee->id, 'role' => 'developer']);
        $task = Task::factory()->create(['project_id' => $project->id]);
        TimeEntry::factory()->create(['user_id' => $employee->id, 'trackable_type' => Task::class, 'trackable_id' => $task->id, 'end_time' => null]);
        $standalone = Task::factory()->create(['project_id' => null]);
        TimeEntry::factory()->create(['trackable_type' => Task::class, 'trackable_id' => $standalone->id, 'end_time' => null]);
        $working = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/dashboard/overview')->assertOk()
            ->assertJsonPath('data.liveWork.activeTimerCount', 2)->json('data.liveWork.working');
        $this->assertContains($employee->id, array_column($working, 'userId'));
        $response = $this->actingAs($employee, 'sanctum')->getJson('/api/v1/dashboard/overview')->assertOk()
            ->assertJsonFragment(['projectId' => $project->id, 'name' => $project->title, 'deadline' => '2026-11-01']);
        $this->assertNotContains($other->id, array_column($response->json('data.projects'), 'projectId'));
    }

    public function test_lan_bridge_defaults_to_shadow_then_imports_idempotently_without_device_writes(): void
    {
        $device = Device::factory()->create(['is_active' => true, 'meta' => ['bridge_token_hash' => hash('sha256', 'office-token'), 'bridge_mode' => 'shadow']]);
        $payload = ['events' => [['employeeNoString' => '7', 'time' => '2026-10-25T09:00:00+03:00', 'serialNo' => 123, 'major' => 5, 'minor' => 75]]];
        $url = '/api/v1/device-bridge/'.$device->id.'/events';
        $this->postJson($url, $payload)->assertUnauthorized();
        $this->withToken('office-token')->postJson($url, $payload)->assertOk()->assertJsonPath('data.applied', false);
        $this->assertDatabaseCount('device_access_events', 0);
        $this->assertDatabaseCount('attendances', 0);
        $device->update(['meta' => [...$device->meta, 'bridge_mode' => 'attendance']]);
        $this->withToken('office-token')->postJson($url, $payload)->assertOk()->assertJsonPath('data.applied', true);
        $this->withToken('office-token')->postJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('device_access_events', 1);
        // Unmapped terminal employee numbers never create attendance for another user.
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_admin_role_name_does_not_bypass_device_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/devices')->assertForbidden();
        $admin->givePermissionTo(Permission::findOrCreate('manage_devices', 'web'));
        $this->getJson('/api/v1/devices')->assertOk();
    }
}
