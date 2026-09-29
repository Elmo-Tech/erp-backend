<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\EmployeeLateDeduction;
use App\Models\EmployeeShortPermission;
use App\Models\HrPolicy;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeLateDeductionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoints_require_employee_management_and_respect_target_hierarchy(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();
        $peerManager = $this->employeeManager();
        $unprivileged = User::factory()->create();

        $this->getJson("/api/v1/employees/{$employee->id}/late-deductions?year=2026&month=10")
            ->assertUnauthorized();
        $this->actingAs($unprivileged, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/late-deductions/evaluate", ['year' => 2026, 'month' => 10])
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$peerManager->id}/late-deductions?year=2026&month=10")
            ->assertForbidden();
    }

    public function test_evaluation_snapshots_policy_daily_rate_and_uses_the_fourth_percentage_for_every_later_occurrence(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 6000]);
        $policy = $this->policy([
            'dailyRateDivisor' => 20,
            'lateDeductionTiers' => [
                ['minimumMinutes' => 15, 'maximumMinutes' => 29, 'percentages' => [10, 20, 30, 40]],
                ['minimumMinutes' => 30, 'maximumMinutes' => null, 'percentages' => [20, 30, 40, 50]],
            ],
        ]);

        foreach (range(1, 5) as $day) {
            $this->attendance($employee, sprintf('2026-10-%02d', $day), 30);
        }

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/late-deductions/evaluate", ['year' => 2026, 'month' => 10])
            ->assertOk();
        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/late-deductions?year=2026&month=10")
            ->assertOk()
            ->assertJsonCount(5, 'data.records')
            ->assertJsonPath('data.records.0.dailyRate', 300)
            ->assertJsonPath('data.records.3.occurrenceNumber', 4)
            ->assertJsonPath('data.records.4.occurrenceNumber', 5)
            ->assertJsonPath('data.records.4.percentage', 50)
            ->assertJsonPath('data.summary.calculatedAmount', 570)
            ->assertJsonPath('data.summary.adjustedAmount', 570)
            ->assertJsonPath('data.summary.waivedCount', 0)
            ->assertJsonPath('data.summary.evaluatedCount', 5);

        $records = EmployeeLateDeduction::query()->orderBy('attendance_date')->get();

        $this->assertSame([1, 2, 3, 4, 5], $records->pluck('occurrence_number')->all());
        $this->assertSame([20.0, 30.0, 40.0, 50.0, 50.0], $records->map(fn (EmployeeLateDeduction $record): float => (float) $record->percentage)->all());
        $this->assertSame([60.0, 90.0, 120.0, 150.0, 150.0], $records->map(fn (EmployeeLateDeduction $record): float => (float) $record->calculated_amount)->all());
        $this->assertSame($policy->id, $records->first()->policy_version_id);
        $this->assertSame($policy->settings, $records->first()->policy_snapshot);
        $this->assertDatabaseCount('employee_deductions', 0);
        $this->assertDatabaseCount('salary_adjustment_transactions', 5);
    }

    public function test_malformed_active_policy_returns_a_clear_validation_error_without_a_fallback(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true]);
        $this->attendance($employee, '2026-10-01', 30);
        HrPolicy::create([
            'settings' => [
                'dailyRateDivisor' => 0,
                'lateDeductionTiers' => [],
            ],
            'effective_from' => today()->toDateString(),
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/late-deductions/evaluate", ['year' => 2026, 'month' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('policy');

        $this->assertDatabaseCount('employee_late_deductions', 0);
        $this->assertDatabaseCount('salary_adjustment_transactions', 0);
    }

    public function test_only_pre_recorded_paid_permissions_reduce_lateness_and_waived_events_do_not_consume_occurrences(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 3000]);
        $this->policy();
        $firstAttendance = $this->attendance($employee, '2026-10-02', 30);
        $secondAttendance = $this->attendance($employee, '2026-10-03', 30);

        $this->approvedPermission($employee, '2026-10-02', '09:10', '09:20', true, '2026-10-01 12:00:00', '2026-10-01 13:00:00');
        $this->approvedPermission($employee, '2026-10-02', '09:00', '09:30', false, '2026-10-01 12:00:00', '2026-10-01 13:00:00');
        $this->approvedPermission($employee, '2026-10-02', '09:00', '09:30', true, '2026-10-01 12:00:00', '2026-10-02 00:01:00');
        $this->approvedPermission($employee, '2026-10-03', '09:00', '09:20', true, '2026-10-01 12:00:00', '2026-10-01 13:00:00');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/late-deductions/evaluate", ['year' => 2026, 'month' => 10])
            ->assertOk()
            ->assertJsonPath('data.records.0.attendanceId', $firstAttendance->id)
            ->assertJsonPath('data.records.0.eligibleLateMinutes', 20)
            ->assertJsonPath('data.records.0.occurrenceNumber', 1)
            ->assertJsonPath('data.records.0.status', EmployeeLateDeduction::STATUS_CALCULATED)
            ->assertJsonPath('data.records.0.calculatedAmount', 10)
            ->assertJsonPath('data.records.1.attendanceId', $secondAttendance->id)
            ->assertJsonPath('data.records.1.eligibleLateMinutes', 10)
            ->assertJsonPath('data.records.1.occurrenceNumber', null)
            ->assertJsonPath('data.records.1.status', EmployeeLateDeduction::STATUS_WAIVED)
            ->assertJsonPath('data.records.1.salaryAdjustmentId', null)
            ->assertJsonPath('data.summary.waivedCount', 1);

        $this->assertDatabaseCount('salary_adjustment_transactions', 1);
    }

    public function test_overlapping_paid_permissions_are_merged_before_reducing_lateness(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 3000]);
        $this->policy();
        $attendance = $this->attendance($employee, '2026-10-04', 60);

        $this->approvedPermission($employee, '2026-10-04', '09:00', '09:20', true, '2026-10-03 12:00:00', '2026-10-03 13:00:00');
        $this->approvedPermission($employee, '2026-10-04', '09:15', '09:30', true, '2026-10-03 12:00:00', '2026-10-03 13:00:00');
        $this->approvedPermission($employee, '2026-10-04', '09:40', '09:50', true, '2026-10-03 12:00:00', '2026-10-03 13:00:00');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/late-deductions/evaluate", ['year' => 2026, 'month' => 10])
            ->assertOk()
            ->assertJsonPath('data.records.0.attendanceId', $attendance->id)
            ->assertJsonPath('data.records.0.originalLateMinutes', 60)
            ->assertJsonPath('data.records.0.eligibleLateMinutes', 20)
            ->assertJsonPath('data.records.0.status', EmployeeLateDeduction::STATUS_CALCULATED);
    }

    public function test_evaluation_is_idempotent_and_creates_exactly_one_linked_adjustment_per_non_waived_record(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 3000]);
        $this->policy();
        $attendance = $this->attendance($employee, '2026-10-05', 30);

        foreach (range(1, 2) as $attempt) {
            $this->actingAs($manager, 'sanctum')
                ->postJson("/api/v1/employees/{$employee->id}/late-deductions/evaluate", ['year' => 2026, 'month' => 10])
                ->assertOk()
                ->assertJsonCount(1, 'data.records');
        }

        $record = EmployeeLateDeduction::where('attendance_id', $attendance->id)->sole();

        $this->assertDatabaseCount('employee_late_deductions', 1);
        $this->assertDatabaseCount('salary_adjustment_transactions', 1);
        $this->assertDatabaseHas('salary_adjustment_transactions', [
            'id' => $record->salary_adjustment_id,
            'reference_type' => EmployeeLateDeduction::class,
            'reference_id' => $record->id,
            'status' => SalaryAdjustment::STATUS_APPROVED,
        ]);
        $this->assertDatabaseCount('employee_deductions', 0);
    }

    public function test_manual_override_updates_the_transaction_zero_cancels_it_and_processed_transactions_are_unchanged(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 3000]);
        $this->policy();
        $firstAttendance = $this->attendance($employee, '2026-10-06', 20);
        $secondAttendance = $this->attendance($employee, '2026-10-07', 20);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/late-deductions/evaluate", ['year' => 2026, 'month' => 10])
            ->assertOk();
        $firstRecord = EmployeeLateDeduction::where('attendance_id', $firstAttendance->id)->sole();
        $secondRecord = EmployeeLateDeduction::where('attendance_id', $secondAttendance->id)->sole();

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/employee-late-deductions/{$firstRecord->id}/override", [
                'amount' => '25.00',
                'reason' => 'Manager verified a reduced deduction.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeLateDeduction::STATUS_OVERRIDDEN)
            ->assertJsonPath('data.overrideAmount', 25)
            ->assertJsonPath('data.finalAmount', 25);
        $this->assertDatabaseHas('salary_adjustment_transactions', [
            'id' => $firstRecord->salary_adjustment_id,
            'amount' => 25,
            'reason' => 'Manager verified a reduced deduction.',
            'status' => SalaryAdjustment::STATUS_APPROVED,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/employee-late-deductions/{$firstRecord->id}/override", [
                'amount' => '0.00',
                'reason' => 'Manager waived the payroll impact.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeLateDeduction::STATUS_OVERRIDDEN)
            ->assertJsonPath('data.overrideAmount', 0)
            ->assertJsonPath('data.finalAmount', 0);
        $this->assertDatabaseHas('salary_adjustment_transactions', [
            'id' => $firstRecord->salary_adjustment_id,
            'amount' => 0,
            'reason' => 'Manager waived the payroll impact.',
            'status' => SalaryAdjustment::STATUS_CANCELLED,
        ]);

        $processedAdjustment = SalaryAdjustment::findOrFail($secondRecord->salary_adjustment_id);
        $processedAdjustment->update(['status' => SalaryAdjustment::STATUS_PROCESSED]);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/employee-late-deductions/{$secondRecord->id}/override", [
                'amount' => '5.00',
                'reason' => 'This must not alter payroll.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('salary_adjustment_transactions', [
            'id' => $processedAdjustment->id,
            'amount' => 20,
            'status' => SalaryAdjustment::STATUS_PROCESSED,
        ]);
        $this->assertNull($secondRecord->fresh()->override_amount);
    }

    private function employeeManager(): User
    {
        $manager = User::factory()->create(['is_employee' => true]);
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));

        return $manager;
    }

    private function policy(array $overrides = []): HrPolicy
    {
        return HrPolicy::create([
            'settings' => array_replace([
                'dailyRateDivisor' => 30,
                'lateDeductionTiers' => [
                    ['minimumMinutes' => 15, 'maximumMinutes' => 29, 'percentages' => [10, 20, 30, 40]],
                    ['minimumMinutes' => 30, 'maximumMinutes' => 44, 'percentages' => [20, 30, 40, 50]],
                    ['minimumMinutes' => 45, 'maximumMinutes' => 59, 'percentages' => [30, 40, 50, 60]],
                    ['minimumMinutes' => 60, 'maximumMinutes' => null, 'percentages' => [40, 50, 60, 70]],
                ],
            ], $overrides),
            'effective_from' => today()->toDateString(),
        ]);
    }

    private function attendance(User $employee, string $date, int $lateMinutes): Attendance
    {
        return Attendance::create([
            'user_id' => $employee->id,
            'date' => $date,
            'clock_in' => Carbon::parse($date.' 09:00:00')->addMinutes($lateMinutes),
            'late_minutes' => $lateMinutes,
            'is_late' => true,
            'idle_minutes' => 0,
            'deduction_minutes' => 0,
            'used_grace_day' => false,
            'expected_clock_in' => '09:00:00',
        ]);
    }

    private function approvedPermission(
        User $employee,
        string $date,
        string $startTime,
        string $endTime,
        bool $isPaid,
        string $createdAt,
        string $reviewedAt,
    ): EmployeeShortPermission {
        return EmployeeShortPermission::forceCreate([
            'employee_id' => $employee->id,
            'permission_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => Carbon::createFromFormat('H:i', $startTime)
                ->diffInMinutes(Carbon::createFromFormat('H:i', $endTime)),
            'is_paid' => $isPaid,
            'status' => EmployeeShortPermission::STATUS_APPROVED,
            'reviewed_at' => $reviewedAt,
            'created_at' => $createdAt,
            'updated_at' => $reviewedAt,
        ]);
    }
}
