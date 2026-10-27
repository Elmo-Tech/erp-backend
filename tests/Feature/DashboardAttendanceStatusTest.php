<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardAttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    public static function attendanceStates(): array
    {
        return [
            'not started' => [false, false, false, false],
            'clocked in' => [true, false, true, false],
            'clocked out' => [true, true, false, true],
        ];
    }

    #[DataProvider('attendanceStates')]
    public function test_dashboard_distinguishes_attendance_states(bool $hasRecord, bool $closed, bool $clockedIn, bool $clockedOut): void
    {
        $this->travelTo(Carbon::parse('2026-10-27 17:00:00', 'Africa/Cairo'));
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('view_dashboard', 'web'));
        if ($hasRecord) {
            Attendance::create([
                'user_id' => $user->id, 'date' => '2026-10-27',
                'clock_in' => '2026-10-27 10:00:00',
                'clock_out' => $closed ? '2026-10-27 16:00:00' : null,
            ]);
        }
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard/overview')
            ->assertOk()->assertJsonPath('data.stats.clockedIn', $clockedIn)
            ->assertJsonPath('data.stats.clockedOut', $clockedOut);
        $this->travelBack();
    }
}
