<?php

namespace Tests\Feature;

use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceSettlement;
use App\Models\HrPolicy;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeAdvanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_routes_use_sanctum_and_allow_only_own_or_hierarchy_authorized_employee_access(): void
    {
        $employee = $this->employee();
        $otherEmployee = $this->employee();
        $manager = $this->employeeManager();
        $peerManager = $this->employeeManager();

        $this->getJson("/api/v1/employees/{$employee->id}/advances?year=2026&month=1")
            ->assertUnauthorized();
        $this->postJson("/api/v1/employees/{$employee->id}/advances", $this->payload())
            ->assertUnauthorized();

        $advanceId = $this->createAdvance($employee, $employee);

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/employees/'.$employee->id.'/advances?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->assertJsonPath('data.advances.0.id', $advanceId)
            ->assertJsonPath('data.summary.requestedAmount', 400)
            ->assertJsonPath('data.summary.pendingCount', 1);
        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employee-advances/{$advanceId}")
            ->assertOk()
            ->assertJsonPath('data.employeeId', $employee->id);
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$otherEmployee->id}/advances", $this->payload())
            ->assertForbidden();
        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employee-advances/{$advanceId}")
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$otherEmployee->id}/advances", $this->payload())
            ->assertCreated();
        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$otherEmployee->id}/advances?year=".now()->year.'&month='.now()->month)
            ->assertOk();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$peerManager->id}/advances", $this->payload())
            ->assertForbidden();
    }

    public function test_creation_validates_inputs_salary_policy_cap_and_server_owned_fields(): void
    {
        $employee = $this->employee(['salary' => 0]);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/advances", $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requestedAmount');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/advances", $this->payload([
                'requestedAmount' => '100.001',
                'status' => EmployeeAdvance::STATUS_APPROVED,
                'installmentCount' => 13,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['requestedAmount', 'status', 'installmentCount']);

        $employee->update(['salary' => 1000]);
        $this->policy([
            'advanceLimitSalaryPercentage' => 50,
            'allowAdvanceInstallments' => false,
        ]);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/advances", $this->payload([
                'requestedAmount' => '500.01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requestedAmount');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/advances", $this->payload([
                'requestedAmount' => '500.00',
                'installmentCount' => 2,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('installmentCount');
    }

    public function test_approval_locks_and_counts_existing_approved_and_disbursed_outstanding_exposure(): void
    {
        $employee = $this->employee(['salary' => 1000]);
        $manager = $this->employeeManager();
        $this->policy(['advanceLimitSalaryPercentage' => 100]);
        $approvedId = $this->createAdvance($employee, $employee, ['requestedAmount' => '300.00']);
        $disbursedId = $this->createAdvance($employee, $employee, ['requestedAmount' => '500.00']);
        $overLimitId = $this->createAdvance($employee, $employee, ['requestedAmount' => '300.00']);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$approvedId}/approve")
            ->assertOk();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$disbursedId}/approve")
            ->assertOk();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$disbursedId}/disburse")
            ->assertOk()
            ->assertJsonPath('data.outstandingAmount', 500);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$overLimitId}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approvedAmount');

        $this->assertDatabaseHas('employee_advances', [
            'id' => $approvedId,
            'status' => EmployeeAdvance::STATUS_APPROVED,
            'approved_amount' => 300,
        ]);
        $this->assertDatabaseHas('employee_advances', [
            'id' => $disbursedId,
            'status' => EmployeeAdvance::STATUS_DISBURSED,
            'disbursed_amount' => 500,
        ]);
    }

    public function test_managers_can_review_advances_they_registered_for_an_employee_but_not_their_own(): void
    {
        $employee = $this->employee();
        $manager = $this->employeeManager();
        $advanceId = $this->createAdvance($manager, $employee);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$advanceId}/approve")
            ->assertOk()->assertJsonPath('data.approvedBy', $manager->id);
        $ownId = $this->createAdvance($manager, $manager);
        $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employee-advances/{$ownId}/approve")->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$ownId}/reject")
            ->assertForbidden();

        $advanceId = $this->createAdvance($manager, $employee);
        $otherManager = $this->employeeManager();
        $this->actingAs($otherManager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$advanceId}/reject", ['reviewNote' => 'Reviewed independently.'])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeAdvance::STATUS_REJECTED)
            ->assertJsonPath('data.approvedBy', $otherManager->id);
        $this->assertDatabaseCount('salary_adjustment_transactions', 0);
    }

    public function test_approval_snapshots_current_policy_salary_and_limit(): void
    {
        $employee = $this->employee(['salary' => 1000]);
        $manager = $this->employeeManager();
        $policy = $this->policy([
            'advanceLimitSalaryPercentage' => 75,
            'advanceSettlement' => 'next_payroll',
            'allowAdvanceInstallments' => false,
            'payrollPaymentDay' => 15,
        ]);
        $advanceId = $this->createAdvance($employee, $employee, ['requestedAmount' => '600.00']);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$advanceId}/approve", ['reviewNote' => 'Within policy limit.'])
            ->assertOk()
            ->assertJsonPath('data.approvedAmount', 600)
            ->assertJsonPath('data.policyVersionId', $policy->id)
            ->assertJsonPath('data.policySnapshot.advanceLimitSalaryPercentage', 75)
            ->assertJsonPath('data.policySnapshot.advanceSettlement', 'next_payroll')
            ->assertJsonPath('data.policySnapshot.allowAdvanceInstallments', false)
            ->assertJsonPath('data.policySnapshot.payrollPaymentDay', 15)
            ->assertJsonPath('data.salaryAtApproval', 1000)
            ->assertJsonPath('data.advanceLimitAmount', 750);
    }

    public function test_disbursement_creates_exact_monthly_recovery_schedule_after_payroll_without_adjustments(): void
    {
        Carbon::setTestNow('2026-01-16 10:00:00');
        $employee = $this->employee(['salary' => 2000]);
        $manager = $this->employeeManager();
        $this->policy(['payrollPaymentDay' => 15]);
        $advanceId = $this->createAdvance($employee, $employee, [
            'requestedAmount' => '1000.01',
            'installmentCount' => 3,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$advanceId}/approve")
            ->assertOk();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$advanceId}/disburse", ['disbursementReference' => 'CASH-100'])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeAdvance::STATUS_DISBURSED)
            ->assertJsonPath('data.disbursedAmount', 1000.01)
            ->assertJsonPath('data.disbursementReference', 'CASH-100')
            ->assertJsonPath('data.settlements.0.dueOn', '2026-02-15')
            ->assertJsonPath('data.settlements.0.scheduledAmount', 333.33)
            ->assertJsonPath('data.settlements.1.dueOn', '2026-03-15')
            ->assertJsonPath('data.settlements.1.scheduledAmount', 333.33)
            ->assertJsonPath('data.settlements.2.dueOn', '2026-04-15')
            ->assertJsonPath('data.settlements.2.scheduledAmount', 333.35);

        $this->assertDatabaseCount('employee_advance_settlements', 3);
        $this->assertDatabaseCount('salary_adjustment_transactions', 0);
    }

    public function test_settlement_creates_one_negative_adjustment_and_settles_the_advance_after_its_last_installment(): void
    {
        $employee = $this->employee(['salary' => 1000]);
        $manager = $this->employeeManager();
        $advanceId = $this->createAdvance($employee, $employee, [
            'requestedAmount' => '100.00',
            'installmentCount' => 2,
        ]);

        $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employee-advances/{$advanceId}/approve")->assertOk();
        $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employee-advances/{$advanceId}/disburse")->assertOk();
        $settlements = EmployeeAdvance::findOrFail($advanceId)->settlements;
        $firstSettlement = $settlements->first();
        $lastSettlement = $settlements->last();

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employee-advance-settlements/{$firstSettlement->id}/settle")
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advance-settlements/{$firstSettlement->id}/settle", [
                'settlementReference' => 'PAYROLL-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeAdvance::STATUS_DISBURSED)
            ->assertJsonPath('data.outstandingAmount', 50);

        $adjustment = SalaryAdjustment::sole();
        $this->assertSame(SalaryAdjustment::TYPE_ADVANCE_RECOVERY, $adjustment->adjustment_type);
        $this->assertSame(EmployeeAdvanceSettlement::class, $adjustment->reference_type);
        $this->assertSame($firstSettlement->id, $adjustment->reference_id);
        $this->assertSame('50.00', $adjustment->amount);
        $this->assertTrue($adjustment->isNegative());
        $this->assertSame(-50.0, $adjustment->signed_amount);
        $this->assertDatabaseHas('employee_advance_settlements', [
            'id' => $firstSettlement->id,
            'status' => EmployeeAdvanceSettlement::STATUS_SETTLED,
            'salary_adjustment_id' => $adjustment->id,
            'settlement_reference' => 'PAYROLL-1',
        ]);

        $manager->assignRole(Role::findOrCreate('hr_manager', 'web'));
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/hr/salary-adjustments/{$adjustment->id}/cancel", ['reason' => 'Incorrect recovery.'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot cancel this adjustment');
        $this->assertSame(SalaryAdjustment::STATUS_APPROVED, $adjustment->fresh()->status);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advance-settlements/{$firstSettlement->id}/settle")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('salary_adjustment_transactions', 1);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advance-settlements/{$lastSettlement->id}/settle")
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeAdvance::STATUS_SETTLED)
            ->assertJsonPath('data.outstandingAmount', 0);
        $this->assertDatabaseCount('salary_adjustment_transactions', 2);
    }

    public function test_cancellation_respects_employee_and_manager_state_rules(): void
    {
        $employee = $this->employee();
        $manager = $this->employeeManager();
        $pendingId = $this->createAdvance($employee, $employee);
        $approvedId = $this->createAdvance($employee, $employee, ['requestedAmount' => '300.00']);
        $disbursedId = $this->createAdvance($employee, $employee, ['requestedAmount' => '200.00']);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$pendingId}/cancel", ['reviewNote' => 'No longer needed.'])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeAdvance::STATUS_CANCELLED);
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$approvedId}/approve")
            ->assertOk();
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$approvedId}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$approvedId}/cancel", ['reviewNote' => 'Cancelled before payout.'])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeAdvance::STATUS_CANCELLED);

        $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employee-advances/{$disbursedId}/approve")->assertOk();
        $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employee-advances/{$disbursedId}/disburse")->assertOk();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-advances/{$disbursedId}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function employee(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'is_employee' => true,
            'salary' => 1000,
        ], $attributes));
    }

    private function employeeManager(): User
    {
        $manager = $this->employee();
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));

        return $manager;
    }

    private function policy(array $overrides): HrPolicy
    {
        return HrPolicy::create([
            'settings' => array_merge(HrPolicy::sole()->settings, $overrides),
            'effective_from' => today()->toDateString(),
        ]);
    }

    private function createAdvance(User $actor, User $employee, array $overrides = []): int
    {
        return $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/advances", $this->payload($overrides))
            ->assertCreated()
            ->json('data.id');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'requestedAmount' => '400.00',
            'reason' => 'Unexpected medical expense.',
            'installmentCount' => 1,
        ], $overrides);
    }
}
