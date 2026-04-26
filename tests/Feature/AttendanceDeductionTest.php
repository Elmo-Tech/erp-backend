<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\DeductionType;
use App\Models\EmployeeDeduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendanceDeductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_deduction_falls_back_to_default_type_when_submitted_type_id_is_invalid(): void
    {
        Permission::firstOrCreate(['name' => 'manage_attendance']);

        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_attendance');

        $employee = User::factory()->create([
            'salary' => 24000,
            'work_hours_per_day' => 8,
        ]);

        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'date' => '2026-04-26',
            'clock_in' => '2026-04-26 10:30:00',
            'late_minutes' => 30,
            'is_late' => true,
            'idle_minutes' => 0,
            'deduction_minutes' => 0,
            'used_grace_day' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/attendance/{$attendance->id}/deduction", [
                'deduction_type_id' => 1,
                'reason' => 'Manual deduction from attendance management',
                'late_minutes' => 30,
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Deduction added successfully',
            ]);

        $deductionType = DeductionType::where('code', 'ATTENDANCE_DEDUCTION')->firstOrFail();

        $this->assertDatabaseHas('employee_deductions', [
            'user_id' => $employee->id,
            'deduction_type_id' => $deductionType->id,
            'attendance_id' => $attendance->id,
            'late_minutes' => 30,
            'deductible_type' => Attendance::class,
            'deductible_id' => $attendance->id,
            'status' => EmployeeDeduction::STATUS_APPROVED,
        ]);

        $this->assertEquals(30, $attendance->fresh()->deduction_minutes);
        $this->assertEquals(50.00, (float) EmployeeDeduction::firstOrFail()->amount);
    }
}
