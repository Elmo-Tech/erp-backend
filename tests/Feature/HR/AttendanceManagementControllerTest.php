<?php

namespace Tests\Feature\HR;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendanceManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_all_attendance_permission_returns_other_employees_records(): void
    {
        $viewer = $this->userWithPermissions(['view_all_attendance']);
        $employee = User::factory()->create();
        $attendance = $this->lateAttendance($employee);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/hr/attendance')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $attendance->id)
            ->assertJsonPath('data.data.0.user.id', $employee->id);
    }

    public function test_switching_from_grace_to_deduction_restores_balance_and_calculates_minutes(): void
    {
        $manager = $this->userWithPermissions(['manage_attendance']);
        $employee = User::factory()->create([
            'grace_days_allowed' => 2,
            'grace_days_used' => 0,
        ]);
        $attendance = $this->lateAttendance($employee);

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/hr/attendance/{$attendance->id}", ['lateTreatment' => 'grace'])
            ->assertOk()
            ->assertJsonPath('data.usedGraceDay', true)
            ->assertJsonPath('data.deductionMinutes', 0);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'grace_days_used' => 1]);

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/hr/attendance/{$attendance->id}", ['lateTreatment' => 'deduct'])
            ->assertOk()
            ->assertJsonPath('data.usedGraceDay', false)
            ->assertJsonPath('data.deductionMinutes', 45);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'grace_days_used' => 0]);
    }

    public function test_grace_treatment_is_rejected_when_balance_is_exhausted(): void
    {
        $manager = $this->userWithPermissions(['manage_attendance']);
        $employee = User::factory()->create([
            'grace_days_allowed' => 2,
            'grace_days_used' => 2,
            'grace_days_reset_date' => now()->startOfMonth(),
        ]);
        $attendance = $this->lateAttendance($employee);

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/hr/attendance/{$attendance->id}", ['lateTreatment' => 'grace'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lateTreatment');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'used_grace_day' => false,
            'deduction_minutes' => 0,
        ]);
    }

    private function lateAttendance(User $employee): Attendance
    {
        return Attendance::create([
            'user_id' => $employee->id,
            'date' => '2026-07-13',
            'clock_in' => '2026-07-13 09:47:00',
            'status' => 'late',
            'late_minutes' => 47,
            'is_late' => true,
            'used_grace_day' => false,
            'deduction_minutes' => 0,
            'expected_clock_in' => '09:00:00',
        ]);
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
