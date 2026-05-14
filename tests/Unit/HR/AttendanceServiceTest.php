<?php

namespace Tests\Unit\HR;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\HR\Services\AttendanceService;
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
}
