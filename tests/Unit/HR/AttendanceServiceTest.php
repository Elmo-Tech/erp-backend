<?php

namespace Tests\Unit\HR;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\HR\Services\AttendanceService;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_for_today_returns_existing_attendance_without_creating_duplicate(): void
    {
        Carbon::setTestNow('2026-05-13 09:05:00');

        $user = User::factory()->create();

        $existing = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-05-13',
            'clock_in' => Carbon::parse('2026-05-13 08:45:00'),
            'clock_out' => null,
            'status' => 'present',
        ]);

        $attendance = app(AttendanceService::class)->checkInForToday($user);

        $this->assertTrue($existing->is($attendance));
        $this->assertSame('2026-05-13 08:45:00', $attendance->clock_in->toDateTimeString());
        $this->assertDatabaseCount('attendances', 1);

        Carbon::setTestNow();
    }

    public function test_attendance_worked_minutes_include_project_and_personal_time_entries(): void
    {
        $user = User::factory()->create();
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-06-18',
            'clock_in' => '2026-06-18 09:00:00',
            'clock_out' => '2026-06-18 12:00:00',
            'status' => 'present',
        ]);

        $project = Project::factory()->create();
        $projectTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Project task',
            'status' => 'done',
            'assignee_id' => $user->id,
        ]);
        ProjectTimeEntry::create([
            'task_id' => $projectTask->id,
            'user_id' => $user->id,
            'start_time' => '2026-06-18 09:00:00',
            'end_time' => '2026-06-18 10:00:00',
            'duration_minutes' => 60,
            'is_manual' => false,
        ]);

        $personalTask = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Personal task',
            'status' => 'done',
            'priority' => 'medium',
        ]);
        PersonalTimeEntry::create([
            'personal_task_id' => $personalTask->id,
            'user_id' => $user->id,
            'start_time' => '2026-06-18 10:15:00',
            'end_time' => '2026-06-18 11:00:00',
            'duration_minutes' => 45,
            'is_manual' => false,
        ]);

        $this->assertSame(105, $attendance->getWorkedMinutes());
        $this->assertSame(180, $attendance->getPresenceMinutes());
        $this->assertSame(75, $attendance->calculateIdleMinutes());
    }

    public function test_stale_open_attendance_does_not_keep_accumulating_presence(): void
    {
        $user = User::factory()->create();
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-06-18',
            'clock_in' => '2026-06-18 09:00:00',
            'clock_out' => null,
            'status' => 'present',
            'total_worked_minutes' => 120,
        ]);

        $this->assertSame(120, $attendance->getPresenceMinutes(Carbon::parse('2026-06-19 09:00:00')));
    }
}
