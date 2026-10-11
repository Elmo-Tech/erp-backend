<?php

namespace Tests\Feature;

use App\Models\EmployeeLeaveBalance;
use App\Models\HrPolicy;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeaveManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_type_defaults_are_active_and_management_requires_permission(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = User::factory()->create(['is_employee' => true]);
        $payload = [
            'name' => 'Unpaid',
            'slug' => 'unpaid',
            'annualAllowanceDays' => 0,
            'isPaid' => false,
            'isActive' => true,
            'displayOrder' => 4,
        ];

        $this->assertDatabaseHas('leave_types', [
            'name' => 'Annual',
            'slug' => 'annual',
            'annual_allowance_days' => 21,
            'is_paid' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('leave_types', [
            'name' => 'Sick',
            'slug' => 'sick',
            'annual_allowance_days' => 10,
        ]);
        $this->assertDatabaseHas('leave_types', [
            'name' => 'Emergency',
            'slug' => 'emergency',
            'annual_allowance_days' => 6,
        ]);

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/leave-types')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'annual')
            ->assertJsonPath('data.0.annualAllowanceDays', 21);
        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/leave-types/all')
            ->assertForbidden();
        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/leave-types', $payload)
            ->assertForbidden();

        $manager->givePermissionTo(Permission::findOrCreate('manage_leave_types', 'web'));

        $leaveTypeId = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/leave-types', $payload)
            ->assertCreated()
            ->assertJsonPath('data.isPaid', false)
            ->json('data.id');

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/leave-types/{$leaveTypeId}", ['isActive' => false])
            ->assertOk()
            ->assertJsonPath('data.isActive', false);
        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/leave-types')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_employee_can_request_own_leave_but_foreign_requests_require_employee_management(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $otherEmployee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/leaves", $this->leavePayload())
            ->assertCreated()
            ->assertJsonPath('data.employeeId', $employee->id)
            ->assertJsonPath('data.status', 'pending');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$otherEmployee->id}/leaves", $this->leavePayload())
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$otherEmployee->id}/leaves", $this->leavePayload())
            ->assertCreated()
            ->assertJsonPath('data.employeeId', $otherEmployee->id);
    }

    public function test_leave_across_working_and_non_working_days_consumes_balance_only_after_approval(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();
        HrPolicy::create([
            'settings' => ['workingDays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']],
            'effective_from' => '2026-01-01',
        ]);
        $id = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/leaves", $this->leavePayload([
                'startDate' => '2026-10-09', 'endDate' => '2026-10-12',
            ]))
            ->assertCreated()->assertJsonPath('data.workingDays', 2)->json('data.id');
        $this->getJson("/api/v1/employees/{$employee->id}/leaves?year=2026")
            ->assertOk()->assertJsonPath('data.balances.0.remainingDays', 21);
        $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employee-leaves/{$id}/approve")
            ->assertOk()->assertJsonPath('data.workingDays', 2);
        $this->getJson("/api/v1/employees/{$employee->id}/leaves?year=2026")
            ->assertOk()->assertJsonPath('data.balances.0.remainingDays', 19);
    }

    public function test_approval_uses_current_policy_working_days_and_snapshots_its_version(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();
        $policy = HrPolicy::create([
            'settings' => ['workingDays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']],
            'effective_from' => today()->toDateString(),
        ]);
        $leaveId = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/leaves", $this->leavePayload([
                'startDate' => '2026-10-04',
                'endDate' => '2026-10-08',
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-leaves/{$leaveId}/approve", ['reviewNote' => 'Approved.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.workingDays', 4)
            ->assertJsonPath('data.isPaid', true)
            ->assertJsonPath('data.reviewedBy', $manager->id);

        $this->assertDatabaseHas('employee_leaves', [
            'id' => $leaveId,
            'working_days' => 4,
            'policy_version_id' => $policy->id,
        ]);
    }

    public function test_pending_and_approved_leave_dates_cannot_overlap(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/leaves", $this->leavePayload())
            ->assertCreated();
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/leaves", $this->leavePayload([
                'startDate' => '2026-10-05',
                'endDate' => '2026-10-07',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('startDate');
    }

    public function test_paid_leave_cannot_exhaust_its_yearly_balance(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();
        $leaveType = $this->annualLeaveType();
        $leaveType->update(['annual_allowance_days' => 2]);
        $firstLeaveId = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/leaves", $this->leavePayload([
                'startDate' => '2026-10-04',
                'endDate' => '2026-10-05',
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-leaves/{$firstLeaveId}/approve")
            ->assertOk();

        $secondLeaveId = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/leaves", $this->leavePayload([
                'startDate' => '2026-10-06',
                'endDate' => '2026-10-06',
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-leaves/{$secondLeaveId}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('workingDays');

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated_days' => 2,
            'used_days' => 2,
        ]);
    }

    public function test_cancelling_approved_paid_leave_releases_the_consumed_balance(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();
        $leaveId = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/leaves", $this->leavePayload([
                'startDate' => '2026-10-04',
                'endDate' => '2026-10-05',
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-leaves/{$leaveId}/approve")
            ->assertOk();
        $this->assertSame('2.00', EmployeeLeaveBalance::sole()->used_days);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employee-leaves/{$leaveId}/cancel", ['reviewNote' => 'Plans changed.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.reviewedBy', $employee->id);

        $this->assertSame('0.00', EmployeeLeaveBalance::sole()->fresh()->used_days);
    }

    public function test_history_is_scoped_to_the_requested_employee_and_contains_balance_summary(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $otherEmployee = User::factory()->create(['is_employee' => true]);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/leaves", $this->leavePayload())
            ->assertCreated();
        $this->actingAs($otherEmployee, 'sanctum')
            ->postJson("/api/v1/employees/{$otherEmployee->id}/leaves", $this->leavePayload())
            ->assertCreated();

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/leaves?year=2026")
            ->assertOk()
            ->assertJsonCount(1, 'data.leaves')
            ->assertJsonCount(3, 'data.balances')
            ->assertJsonPath('data.leaves.0.employeeId', $employee->id)
            ->assertJsonPath('data.balances.0.leaveType.slug', 'annual')
            ->assertJsonPath('data.balances.0.allocatedDays', 21)
            ->assertJsonPath('data.balances.0.usedDays', 0)
            ->assertJsonPath('data.balances.0.remainingDays', 21);
        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$otherEmployee->id}/leaves?year=2026")
            ->assertForbidden();
    }

    private function employeeManager(): User
    {
        $manager = User::factory()->create(['is_employee' => true]);
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));

        return $manager;
    }

    private function annualLeaveType(): LeaveType
    {
        return LeaveType::query()->where('slug', 'annual')->firstOrFail();
    }

    private function leavePayload(array $overrides = []): array
    {
        return array_merge([
            'leaveTypeId' => $this->annualLeaveType()->id,
            'startDate' => '2026-10-04',
            'endDate' => '2026-10-05',
            'reason' => 'Personal leave.',
        ], $overrides);
    }
}
