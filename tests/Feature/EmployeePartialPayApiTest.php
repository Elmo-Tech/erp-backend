<?php

namespace Tests\Feature;

use App\Models\EmployeePartialPay;
use App\Models\EmploymentContract;
use App\Models\HrPolicy;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeePartialPayApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_month_preview_uses_inclusive_calendar_days_without_persisting_a_record(): void
    {
        $manager = $this->employeeManager();
        $employee = $this->employee(['salary' => 9999]);
        $contract = $this->contract($employee, [
            'base_salary' => 3000,
            'start_date' => '2026-02-15',
            'end_date' => '2027-02-14',
        ]);
        $policy = $this->policy([
            'dailyRateDivisor' => 20,
            'midMonthHirePaidDays' => 15,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/partial-pays/preview", $this->payload($contract))
            ->assertOk()
            ->assertJsonPath('data.employeeId', $employee->id)
            ->assertJsonPath('data.employmentContractId', $contract->id)
            ->assertJsonPath('data.kind', EmployeePartialPay::KIND_FIRST_MONTH)
            ->assertJsonPath('data.serviceStartDate', '2026-02-15')
            ->assertJsonPath('data.serviceEndDate', '2026-02-28')
            ->assertJsonPath('data.payableDays', 14)
            ->assertJsonPath('data.baseSalarySnapshot', 3000)
            ->assertJsonPath('data.dailyRateSnapshot', 107.142857)
            ->assertJsonPath('data.grossAmount', 1500)
            ->assertJsonPath('data.policyVersionId', $policy->id)
            ->assertJsonPath('data.policySnapshot.dailyRateDivisor', 28)
            ->assertJsonPath('data.policySnapshot.midMonthHirePaidDays', 15)
            ->assertJsonPath('data.contractWorkDays', ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday']);

        $this->assertDatabaseCount('employee_partial_pays', 0);
        $this->assertDatabaseCount('salary_adjustment_transactions', 0);
    }

    public function test_first_month_proration_supports_any_contract_start_date(): void
    {
        $manager = $this->employeeManager();
        $employee = $this->employee();
        $contract = $this->contract($employee, [
            'start_date' => '2026-02-14',
            'end_date' => '2027-02-13',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/partial-pays/preview", $this->payload($contract))
            ->assertOk()
            ->assertJsonPath('data.payableDays', 15)
            ->assertJsonPath('data.grossAmount', 2678.57);
    }

    public function test_termination_preview_uses_the_last_scheduled_workday_before_a_nonworking_termination_date(): void
    {
        $manager = $this->employeeManager();
        $employee = $this->employee();
        $contract = $this->contract($employee, [
            'base_salary' => 3000,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'status' => EmploymentContract::STATUS_TERMINATED,
            'terminated_on' => '2026-05-17',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/partial-pays/preview", $this->payload($contract, [
                'kind' => EmployeePartialPay::KIND_TERMINATION,
                'payrollYear' => 2026,
                'payrollMonth' => 5,
            ]))
            ->assertOk()
            ->assertJsonPath('data.serviceStartDate', '2026-05-01')
            ->assertJsonPath('data.serviceEndDate', '2026-05-15')
            ->assertJsonPath('data.payableDays', 15)
            ->assertJsonPath('data.dailyRateSnapshot', 100)
            ->assertJsonPath('data.grossAmount', 1500)
            ->assertJsonPath('data.contractWorkDays', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
    }

    public function test_partial_pay_uses_only_a_positive_contract_base_salary(): void
    {
        $manager = $this->employeeManager();
        $employee = $this->employee(['salary' => 5000]);
        $contract = $this->contract($employee, [
            'base_salary' => 0,
            'start_date' => '2026-02-15',
            'end_date' => '2027-02-14',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/partial-pays/preview", $this->payload($contract))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contractId');
    }

    public function test_history_authorization_hierarchy_self_approval_and_record_uniqueness_are_enforced(): void
    {
        $employee = $this->employee();
        $manager = $this->employeeManager();
        $peerManager = $this->employeeManager();
        $contract = $this->contract($employee, [
            'start_date' => '2026-02-15',
            'end_date' => '2027-02-14',
        ]);

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/partial-pays?year=2026&month=2")
            ->assertOk()
            ->assertJsonPath('data.records', [])
            ->assertJsonPath('data.summary.draftCount', 0);
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/partial-pays/preview", $this->payload($contract))
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$peerManager->id}/partial-pays/preview", $this->payload($contract))
            ->assertForbidden();

        $recordId = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/partial-pays", $this->payload($contract))
            ->assertCreated()
            ->assertJsonPath('data.status', EmployeePartialPay::STATUS_DRAFT)
            ->json('data.id');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/partial-pays", $this->payload($contract))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payrollMonth');
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-partial-pays/{$recordId}/approve")
            ->assertForbidden();

        $this->actingAs($peerManager, 'sanctum')
            ->postJson("/api/v1/employee-partial-pays/{$recordId}/approve", ['reviewNote' => 'Reviewed independently.'])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeePartialPay::STATUS_APPROVED)
            ->assertJsonPath('data.approvedBy', $peerManager->id)
            ->assertJsonPath('data.reviewNote', 'Reviewed independently.')
            ->assertJsonPath('data.salaryAdjustmentStatus', SalaryAdjustment::STATUS_APPROVED);
        $this->actingAs($peerManager, 'sanctum')
            ->postJson("/api/v1/employee-partial-pays/{$recordId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', EmployeePartialPay::STATUS_APPROVED);

        $adjustment = SalaryAdjustment::where('reference_type', EmployeePartialPay::class)->sole();

        $this->assertSame(SalaryAdjustment::TYPE_PARTIAL_PAY, $adjustment->adjustment_type);
        $this->assertSame('2500.00', $adjustment->amount);
        $this->assertTrue($adjustment->isPositive());
        $this->assertDatabaseCount('employee_partial_pays', 1);
        $this->assertDatabaseCount('salary_adjustment_transactions', 1);

        $peerManager->assignRole(Role::findOrCreate('hr_manager', 'web'));
        $this->actingAs($peerManager, 'sanctum')
            ->postJson("/api/v1/hr/salary-adjustments/{$adjustment->id}/cancel", ['reason' => 'Not in this scope.'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot cancel this adjustment');
        $this->actingAs($peerManager, 'sanctum')
            ->postJson("/api/v1/employee-partial-pays/{$recordId}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_created_partial_pay_snapshots_do_not_change_after_policy_or_contract_edits(): void
    {
        $manager = $this->employeeManager();
        $employee = $this->employee();
        $contract = $this->contract($employee, [
            'base_salary' => 3000,
            'start_date' => '2026-02-15',
            'end_date' => '2027-02-14',
            'work_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
        ]);
        $policy = $this->policy([
            'dailyRateDivisor' => 30,
            'midMonthHirePaidDays' => 15,
        ]);

        $recordId = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/partial-pays", $this->payload($contract))
            ->assertCreated()
            ->assertJsonPath('data.policyVersionId', $policy->id)
            ->json('data.id');

        $contract->update([
            'base_salary' => 6000,
            'work_days' => ['friday'],
        ]);
        $this->policy([
            'dailyRateDivisor' => 20,
            'midMonthHirePaidDays' => 10,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/partial-pays?year=2026&month=2")
            ->assertOk()
            ->assertJsonPath('data.records.0.id', $recordId)
            ->assertJsonPath('data.records.0.baseSalarySnapshot', 3000)
            ->assertJsonPath('data.records.0.dailyRateSnapshot', 107.142857)
            ->assertJsonPath('data.records.0.grossAmount', 1500)
            ->assertJsonPath('data.records.0.policyVersionId', $policy->id)
            ->assertJsonPath('data.records.0.policySnapshot.dailyRateDivisor', 28)
            ->assertJsonPath('data.records.0.policySnapshot.midMonthHirePaidDays', 15)
            ->assertJsonPath('data.records.0.contractWorkDays', ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday']);
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

    private function contract(User $employee, array $overrides = []): EmploymentContract
    {
        return EmploymentContract::create(array_merge([
            'employee_id' => $employee->id,
            'employment_type' => 'full_time',
            'base_salary' => 5000,
            'start_date' => '2026-02-15',
            'end_date' => '2027-02-14',
            'duration_months' => 12,
            'work_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'work_hours_per_day' => 8,
            'work_start_time' => '09:00:00',
            'status' => EmploymentContract::STATUS_DRAFT,
        ], $overrides));
    }

    private function policy(array $overrides): HrPolicy
    {
        return HrPolicy::create([
            'settings' => array_merge(HrPolicy::query()->latest('id')->firstOrFail()->settings, $overrides),
            'effective_from' => today()->toDateString(),
        ]);
    }

    private function payload(EmploymentContract $contract, array $overrides = []): array
    {
        return array_merge([
            'contractId' => $contract->id,
            'kind' => EmployeePartialPay::KIND_FIRST_MONTH,
            'payrollYear' => 2026,
            'payrollMonth' => 2,
        ], $overrides);
    }
}
