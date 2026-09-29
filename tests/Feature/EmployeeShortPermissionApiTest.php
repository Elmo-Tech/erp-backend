<?php

namespace Tests\Feature;

use App\Models\EmployeePermissionAllowance;
use App\Models\HrPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeShortPermissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_can_manage_only_their_own_requests_while_managers_can_manage_targets(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $otherEmployee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/short-permissions", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.employeeId', $employee->id)
            ->assertJsonPath('data.status', 'pending');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$otherEmployee->id}/short-permissions", $this->payload())
            ->assertForbidden();
        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$otherEmployee->id}/short-permissions?month=2026-10")
            ->assertForbidden();

        $managerPermissionId = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$manager->id}/short-permissions", $this->payload())
            ->assertCreated()
            ->json('data.id');
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$managerPermissionId}/approve", ['compensationType' => 'paid'])
            ->assertForbidden();

        $permissionId = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$otherEmployee->id}/short-permissions", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.employeeId', $otherEmployee->id)
            ->json('data.id');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$permissionId}/cancel", ['reviewNote' => 'Entered by mistake.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.reviewedBy', $manager->id);
    }

    public function test_duration_is_derived_and_pending_or_approved_ranges_cannot_overlap(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/short-permissions", $this->payload([
                'durationMinutes' => 999,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.durationMinutes', 60);
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/short-permissions", $this->payload([
                'startTime' => '10:30',
                'endTime' => '11:30',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('startTime');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/short-permissions", $this->payload([
                'startTime' => '11:00',
                'endTime' => '12:00',
            ]))
            ->assertCreated();
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/short-permissions", $this->payload([
                'startTime' => '11:00',
                'endTime' => '11:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endTime');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/short-permissions", $this->payload([
                'startTime' => '12:00',
                'endTime' => '24:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endTime');
    }

    public function test_paid_approval_initializes_the_monthly_policy_allowance_and_snapshots_versions(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();
        $policy = $this->policyWithMonthlyHours(3);
        $permissionId = $this->createRequest($employee);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$permissionId}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('compensationType');
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$permissionId}/approve", [
                'compensationType' => 'paid',
                'reviewNote' => 'Approved as paid.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.isPaid', true)
            ->assertJsonPath('data.policyVersionId', $policy->id)
            ->assertJsonPath('data.allowance.period', '2026-10')
            ->assertJsonPath('data.allowance.allocatedMinutes', 180)
            ->assertJsonPath('data.allowance.usedPaidMinutes', 60)
            ->assertJsonPath('data.allowance.remainingMinutes', 120)
            ->assertJsonPath('data.allowance.overageMinutes', 0);

        $this->assertDatabaseHas('employee_permission_allowances', [
            'employee_id' => $employee->id,
            'period' => '2026-10',
            'allocated_minutes' => 180,
            'used_paid_minutes' => 60,
            'policy_version_id' => $policy->id,
        ]);
        $this->assertDatabaseHas('employee_short_permissions', [
            'id' => $permissionId,
            'policy_version_id' => $policy->id,
        ]);
    }

    public function test_paid_approval_remains_valid_when_it_exceeds_the_monthly_allowance(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();
        $this->policyWithMonthlyHours(1);
        $firstPermissionId = $this->createRequest($employee, [
            'startTime' => '10:00',
            'endTime' => '10:45',
        ]);
        $secondPermissionId = $this->createRequest($employee, [
            'startTime' => '11:00',
            'endTime' => '11:30',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$firstPermissionId}/approve", ['compensationType' => 'paid'])
            ->assertOk();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$secondPermissionId}/approve", ['compensationType' => 'paid'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.allowance.allocatedMinutes', 60)
            ->assertJsonPath('data.allowance.usedPaidMinutes', 75)
            ->assertJsonPath('data.allowance.remainingMinutes', 0)
            ->assertJsonPath('data.allowance.overageMinutes', 15)
            ->assertJsonPath('data.remainingMinutes', 0)
            ->assertJsonPath('data.overageMinutes', 15);

        $this->assertSame(75, EmployeePermissionAllowance::sole()->used_paid_minutes);
    }

    public function test_unpaid_approval_does_not_create_or_consume_an_allowance_ledger(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();
        $this->policyWithMonthlyHours(2);
        $permissionId = $this->createRequest($employee);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$permissionId}/approve", [
                'compensationType' => 'unpaid',
            ])
            ->assertOk()
            ->assertJsonPath('data.isPaid', false)
            ->assertJsonPath('data.allowance.allocatedMinutes', 120)
            ->assertJsonPath('data.allowance.usedPaidMinutes', 0);

        $this->assertDatabaseMissing('employee_permission_allowances', [
            'employee_id' => $employee->id,
            'period' => '2026-10',
        ]);
    }

    public function test_cancelling_an_approved_paid_request_releases_its_minutes_once(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $manager = $this->employeeManager();
        $this->policyWithMonthlyHours(2);
        $permissionId = $this->createRequest($employee, [
            'startTime' => '10:00',
            'endTime' => '10:45',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$permissionId}/approve", ['compensationType' => 'paid'])
            ->assertOk();
        $this->assertSame(45, EmployeePermissionAllowance::sole()->used_paid_minutes);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$permissionId}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$permissionId}/cancel", ['reviewNote' => 'Plans changed.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.reviewedBy', $employee->id);
        $this->assertSame(0, EmployeePermissionAllowance::sole()->fresh()->used_paid_minutes);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employee-short-permissions/{$permissionId}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame(0, EmployeePermissionAllowance::sole()->fresh()->used_paid_minutes);
    }

    public function test_history_includes_camel_case_permission_data_and_policy_allocation_without_a_ledger(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $otherEmployee = User::factory()->create(['is_employee' => true]);
        $this->policyWithMonthlyHours(4);
        $this->createRequest($employee);
        $this->createRequest($otherEmployee);

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/short-permissions?month=2026-10")
            ->assertOk()
            ->assertJsonCount(1, 'data.permissions')
            ->assertJsonPath('data.permissions.0.employeeId', $employee->id)
            ->assertJsonPath('data.permissions.0.permissionDate', '2026-10-14')
            ->assertJsonPath('data.permissions.0.startTime', '10:00')
            ->assertJsonPath('data.permissions.0.endTime', '11:00')
            ->assertJsonPath('data.permissions.0.durationMinutes', 60)
            ->assertJsonPath('data.permissions.0.isPaid', null)
            ->assertJsonPath('data.permissions.0.status', 'pending')
            ->assertJsonPath('data.permissions.0.policyVersionId', null)
            ->assertJsonPath('data.allowance.period', '2026-10')
            ->assertJsonPath('data.allowance.allocatedMinutes', 240)
            ->assertJsonPath('data.allowance.usedPaidMinutes', 0)
            ->assertJsonPath('data.allowance.remainingMinutes', 240)
            ->assertJsonPath('data.allowance.overageMinutes', 0);
    }

    public function test_allowance_uses_the_six_hour_fallback_only_when_no_policy_is_effective(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        DB::table('hr_policies')->update([
            'effective_from' => today()->addDay()->toDateString(),
        ]);

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/short-permissions?month=2026-10")
            ->assertOk()
            ->assertJsonPath('data.allowance.allocatedMinutes', 360)
            ->assertJsonPath('data.allowance.usedPaidMinutes', 0);
    }

    private function employeeManager(): User
    {
        $manager = User::factory()->create(['is_employee' => true]);
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));

        return $manager;
    }

    private function policyWithMonthlyHours(int $hours): HrPolicy
    {
        return HrPolicy::create([
            'settings' => ['monthlyPaidPermissionHours' => $hours],
            'effective_from' => today()->toDateString(),
        ]);
    }

    private function createRequest(User $employee, array $overrides = []): int
    {
        return $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/short-permissions", $this->payload($overrides))
            ->assertCreated()
            ->json('data.id');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'permissionDate' => '2026-10-14',
            'startTime' => '10:00',
            'endTime' => '11:00',
            'reason' => 'Personal appointment.',
        ], $overrides);
    }
}
