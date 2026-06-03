<?php

namespace Tests\Feature\Calendar;

use App\Enums\MilestoneStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Calendar\Services\MasterCalendarAggregatorService;
use App\Modules\Client\Models\Client;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MasterCalendarAggregatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_project_milestones_in_calendar_feed(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create(['title' => 'Client Portal Relaunch']);

        $milestone = Milestone::create([
            'project_id' => $project->id,
            'title' => 'Design Sign-off',
            'status' => MilestoneStatus::ACTIVE,
            'progress_percentage' => 40,
            'due_date' => '2026-05-20',
        ]);

        $user = $this->userWithPermissions(['view_calendar_milestones']);

        $items = app(MasterCalendarAggregatorService::class)
            ->aggregate(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'), $user);

        $this->assertContains('milestone-'.$milestone->id, $items->pluck('id')->all());

        $item = $items->firstWhere('id', 'milestone-'.$milestone->id);

        $this->assertSame('milestone', $item['type']);
        $this->assertSame('Design Sign-off', $item['title']);
        $this->assertSame('2026-05-20', $item['date']);
        $this->assertSame('active', $item['status']);
        $this->assertSame($project->id, $item['meta']['projectId']);
        $this->assertSame('Client Portal Relaunch', $item['meta']['projectTitle']);
        $this->assertSame(40, $item['meta']['progressPercentage']);
    }

    public function test_it_includes_scheduled_meetings_in_calendar_feed(): void
    {
        $creator = User::factory()->create(['name' => 'Mona Scheduler']);
        $attendee = User::factory()->create();
        $client = Client::factory()->create(['display_name' => 'Acme']);

        $meeting = Meeting::create([
            'model_id' => $client->id,
            'model_type' => Client::class,
            'status' => 'scheduled',
            'title' => 'Discovery Call',
            'meeting_type' => 'discovery-call',
            'internal_attendees' => [$attendee->id],
            'external_attendees' => ['primary-contact'],
            'assigned_to' => $attendee->id,
            'created_by' => $creator->id,
            'scheduled_at' => '2026-05-20 14:30:00',
        ]);

        $user = $this->userWithPermissions(['view_calendar_meetings']);

        $items = app(MasterCalendarAggregatorService::class)
            ->aggregate(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'), $user);

        $item = $items->firstWhere('id', 'meeting-'.$meeting->id);

        $this->assertNotNull($item);
        $this->assertSame('meeting', $item['type']);
        $this->assertSame('2026-05-20', $item['date']);
        $this->assertStringStartsWith('2026-05-20T14:30:00', $item['startTime']);
        $this->assertSame('Mona Scheduler', $item['meta']['creatorName']);
    }

    public function test_it_excludes_item_types_without_matching_calendar_permission(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();

        Milestone::create([
            'project_id' => $project->id,
            'title' => 'Hidden milestone',
            'status' => MilestoneStatus::ACTIVE,
            'progress_percentage' => 10,
            'due_date' => '2026-05-20',
        ]);

        $user = $this->userWithPermissions(['view_calendar_tasks']);

        $items = app(MasterCalendarAggregatorService::class)
            ->aggregate(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'), $user);

        $this->assertFalse($items->contains(fn (array $item) => $item['type'] === 'milestone'));
    }

    public function test_it_includes_current_user_attendance_with_worked_tasks(): void
    {
        $user = $this->userWithPermissions(['view_attendance']);
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['title' => 'Business OS']);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Fix calendar attendance',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);
        $personalTask = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Daily planning',
            'status' => 'todo',
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-05-13',
            'clock_in' => '2026-05-13 09:00:00',
            'clock_out' => '2026-05-13 17:15:00',
            'status' => 'present',
            'total_worked_minutes' => 495,
        ]);
        $otherAttendance = Attendance::create([
            'user_id' => $otherUser->id,
            'date' => '2026-05-13',
            'clock_in' => '2026-05-13 10:00:00',
            'status' => 'late',
        ]);
        ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'start_time' => '2026-05-13 09:30:00',
            'end_time' => '2026-05-13 11:00:00',
            'duration_minutes' => 90,
            'is_manual' => false,
        ]);
        ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'start_time' => '2026-05-13 13:00:00',
            'end_time' => '2026-05-13 14:15:00',
            'duration_minutes' => 75,
            'is_manual' => true,
        ]);
        PersonalTimeEntry::create([
            'personal_task_id' => $personalTask->id,
            'user_id' => $user->id,
            'start_time' => '2026-05-13 15:00:00',
            'end_time' => '2026-05-13 15:20:00',
            'duration_minutes' => 20,
            'is_manual' => true,
        ]);

        $items = app(MasterCalendarAggregatorService::class)
            ->aggregate(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'), $user);

        $item = $items->firstWhere('id', 'attendance-'.$attendance->id);

        $this->assertNotNull($item);
        $this->assertSame('attendance', $item['type']);
        $this->assertSame('2026-05-13', $item['date']);
        $this->assertSame('present', $item['status']);
        $this->assertSame(495, $item['meta']['totalWorkedMinutes']);
        $this->assertCount(2, $item['meta']['workedTasks']);
        $this->assertSame('project', $item['meta']['workedTasks'][0]['source']);
        $this->assertSame($task->id, $item['meta']['workedTasks'][0]['taskId']);
        $this->assertSame('Fix calendar attendance', $item['meta']['workedTasks'][0]['title']);
        $this->assertSame('Business OS', $item['meta']['workedTasks'][0]['projectTitle']);
        $this->assertSame(165, $item['meta']['workedTasks'][0]['durationMinutes']);
        $this->assertSame(2, $item['meta']['workedTasks'][0]['entriesCount']);
        $this->assertSame('personal', $item['meta']['workedTasks'][1]['source']);
        $this->assertSame($personalTask->id, $item['meta']['workedTasks'][1]['taskId']);
        $this->assertSame('Daily planning', $item['meta']['workedTasks'][1]['title']);
        $this->assertSame('Personal', $item['meta']['workedTasks'][1]['projectTitle']);
        $this->assertSame(20, $item['meta']['workedTasks'][1]['durationMinutes']);
        $this->assertFalse($items->contains(fn (array $calendarItem) => $calendarItem['id'] === 'attendance-'.$otherAttendance->id));
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
