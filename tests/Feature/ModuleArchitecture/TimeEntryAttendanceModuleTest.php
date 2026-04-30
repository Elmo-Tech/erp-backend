<?php

namespace Tests\Feature\ModuleArchitecture;

use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\Settings\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TimeEntryAttendanceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_time_entry_and_attendance_domains_are_loaded_from_modules(): void
    {
        $expectedClasses = [
            \App\Modules\TimeEntry\Controllers\TimeEntryController::class,
            \App\Modules\TimeEntry\Models\TimeEntry::class,
            \App\Modules\TimeEntry\Actions\StartTimeEntryAction::class,
            \App\Modules\TimeEntry\Actions\StopTimeEntryAction::class,
            \App\Modules\TimeEntry\Actions\LogManualTimeAction::class,
            \App\Modules\TimeEntry\Resources\TimeEntryResource::class,
            \App\Modules\Attendance\Controllers\AttendanceController::class,
            \App\Modules\Attendance\Models\Attendance::class,
            \App\Modules\Attendance\Actions\ClockInAction::class,
            \App\Modules\Attendance\Actions\ClockOutAction::class,
            \App\Modules\Attendance\Resources\AttendanceResource::class,
        ];

        foreach ($expectedClasses as $class) {
            $this->assertTrue(class_exists($class), "Expected {$class} to exist.");
        }

        foreach (['time-entries.stop', 'time-entries.start', 'attendance.clock-out', 'attendance.today'] as $routeName) {
            $this->assertTrue($this->app['router']->getRoutes()->hasNamedRoute($routeName));
        }
    }

    public function test_stop_time_entry_action_sets_end_time_and_floor_duration_minutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27 14:11:59', 'Africa/Cairo'));

        $user = User::factory()->create();
        $timeEntry = TimeEntry::factory()->create([
            'user_id' => $user->id,
            'start_time' => Carbon::parse('2026-04-27 12:10:30', 'Africa/Cairo'),
            'end_time' => null,
            'duration_minutes' => null,
        ]);

        $stopped = app(\App\Modules\TimeEntry\Actions\StopTimeEntryAction::class)->execute($user);

        $this->assertSame($timeEntry->id, $stopped->id);
        $this->assertSame(121, $stopped->duration_minutes);
        $this->assertSame('2026-04-27 14:11:59', $stopped->end_time->format('Y-m-d H:i:s'));
        $this->assertFalse($stopped->isActive());

        Carbon::setTestNow();
    }

    public function test_stop_time_entry_action_rejects_user_without_active_timer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active timer found for this user.');

        app(\App\Modules\TimeEntry\Actions\StopTimeEntryAction::class)->execute(User::factory()->create());
    }
}
