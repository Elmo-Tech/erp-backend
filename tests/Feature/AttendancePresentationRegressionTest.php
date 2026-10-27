<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Services\HR\AttendanceService;
use App\Services\Reporting\EmployeeReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendancePresentationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_clock_in_sets_the_late_flag_without_changing_the_late_minutes(): void
    {
        $user = User::factory()->create(['work_start_time' => '10:00:00', 'work_hours_per_day' => 8, 'salary' => 0]);
        $attendance = app(AttendanceService::class)->clockIn($user, Carbon::parse('2026-10-27 15:17:00', 'Africa/Cairo'));

        $this->assertSame(317, $attendance->fresh()->late_minutes);
        $this->assertTrue($attendance->fresh()->is_late);
    }

    public function test_admin_list_and_employee_history_agree_for_legacy_late_records(): void
    {
        Permission::firstOrCreate(['name' => 'view_all_attendance']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('view_all_attendance');
        $employee = User::factory()->create(['is_employee' => true, 'work_hours_per_day' => 8, 'salary' => 6000]);
        $record = Attendance::create([
            'user_id' => $employee->id, 'date' => '2026-10-27',
            'clock_in' => '2026-10-27 15:17:00', 'clock_out' => '2026-10-27 15:19:00',
            'late_minutes' => 317, 'is_late' => false, 'idle_minutes' => 2,
        ]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/attendance/all?filter[userId]='.$employee->id)
            ->assertOk()->assertJsonPath('data.attendances.0.lateMinutes', 317)
            ->assertJsonPath('data.attendances.0.isLate', true);
        $this->actingAs($employee, 'sanctum')->getJson('/api/v1/attendance/history')
            ->assertOk()->assertJsonPath('data.attendances.0.lateMinutes', 317)
            ->assertJsonPath('data.attendances.0.isLate', true);
        $review = app(EmployeeReviewService::class)->getEmployeeReview($employee->id, [
            'dateFrom' => '2026-10-26', 'dateTo' => '2026-10-28',
        ]);
        $this->assertSame(1, $review['attendance']['lateDays']);
        $this->assertTrue($review['attendance']['attendanceList'][0]['isLate']);
        // Rendering the corrected flag must not recalculate historic payroll/grace days.
        $this->assertFalse($record->fresh()->is_late);
    }

    public function test_today_keeps_the_recorded_idle_minutes_after_clock_out(): void
    {
        $this->travelTo(Carbon::parse('2026-10-27 15:20:00', 'Africa/Cairo'));
        $employee = User::factory()->create();
        Attendance::create([
            'user_id' => $employee->id, 'date' => '2026-10-27',
            'clock_in' => '2026-10-27 15:17:00', 'clock_out' => '2026-10-27 15:19:00',
            'late_minutes' => 317, 'idle_minutes' => 2,
        ]);
        $this->actingAs($employee, 'sanctum')->getJson('/api/v1/attendance/today')
            ->assertOk()->assertJsonPath('data.currentIdleMinutes', 2)
            ->assertJsonPath('data.totalWorkedMinutes', 0)
            ->assertJsonPath('data.attendance.idleMinutes', 2);
        $this->travelBack();
    }
}
