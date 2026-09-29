<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\EmployeeDeduction;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalaryAdjustmentTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_deduction_creates_a_transaction_without_changing_the_legacy_summary(): void
    {
        $admin = $this->administrator();
        $employee = User::factory()->create();
        $deductionType = DeductionType::create([
            'name' => 'Late arrival',
            'code' => 'LATE_ARRIVAL',
            'calculation_type' => DeductionType::CALCULATION_FIXED,
            'amount' => 125.50,
        ]);
        $deduction = EmployeeDeduction::forceCreate([
            'user_id' => $employee->id,
            'deduction_type_id' => $deductionType->id,
            'deduction_date' => '2026-05-15',
            'amount' => 125.50,
            'reason' => 'Late arrival deduction',
            'status' => EmployeeDeduction::STATUS_PENDING,
            'deductible_type' => DeductionType::class,
            'deductible_id' => $deductionType->id,
            'created_by' => $admin->id,
        ]);
        $legacyId = $this->insertLegacySummary($employee, 2026, 5);
        $legacyBefore = DB::table('salary_adjustments')->where('id', $legacyId)->first();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/hr/employee-deductions/{$deduction->id}/approve")
            ->assertOk()
            ->assertJsonPath('message', 'Employee deduction approved successfully');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/hr/employee-deductions/{$deduction->id}/approve")
            ->assertUnprocessable();

        $this->assertDatabaseHas('salary_adjustment_transactions', [
            'user_id' => $employee->id,
            'adjustment_type' => SalaryAdjustment::TYPE_DEDUCTION,
            'reference_type' => EmployeeDeduction::class,
            'reference_id' => $deduction->id,
            'payroll_month' => 5,
            'payroll_year' => 2026,
            'status' => SalaryAdjustment::STATUS_APPROVED,
            'approved_by' => $admin->id,
        ]);
        $transaction = SalaryAdjustment::where('reference_id', $deduction->id)->sole();

        $this->assertSame('125.50', $transaction->amount);
        $this->assertTrue($deduction->fresh()->salaryAdjustment->is($transaction));
        $this->assertDatabaseCount('salary_adjustment_transactions', 1);
        $this->assertDatabaseCount('salary_adjustments', 1);
        $this->assertEquals($legacyBefore, DB::table('salary_adjustments')->where('id', $legacyId)->first());
    }

    public function test_salary_adjustment_list_and_summary_read_transactions_not_legacy_summaries(): void
    {
        $admin = $this->administrator();
        $employee = User::factory()->create(['salary' => 5000]);
        $this->insertLegacySummary($employee, 2026, 4);
        $adjustment = SalaryAdjustment::create([
            'user_id' => $employee->id,
            'adjustment_type' => SalaryAdjustment::TYPE_REWARD,
            'amount' => 175.50,
            'reason' => 'Project completion reward',
            'adjustment_date' => '2026-04-15',
            'payroll_month' => 4,
            'payroll_year' => 2026,
            'status' => SalaryAdjustment::STATUS_APPROVED,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/hr/salary-adjustments?filter[payroll_year]=2026&filter[payroll_month]=4')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $adjustment->id)
            ->assertJsonPath('data.0.amount', '175.50');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/hr/salary-adjustments-payroll-summary?year=2026&month=4')
            ->assertOk()
            ->assertJsonPath('total_employees', 1)
            ->assertJsonPath('total_additions', 175.5)
            ->assertJsonPath('total_deductions', 0)
            ->assertJsonPath('net_adjustment', 175.5)
            ->assertJsonPath('employees.0.adjustments.0.id', $adjustment->id);
    }

    public function test_payroll_summary_includes_partial_pay_and_advance_recovery_totals(): void
    {
        $admin = $this->administrator();
        $employee = User::factory()->create();
        SalaryAdjustment::create([
            'user_id' => $employee->id,
            'adjustment_type' => SalaryAdjustment::TYPE_PARTIAL_PAY,
            'amount' => 300,
            'adjustment_date' => '2026-04-15',
            'payroll_month' => 4,
            'payroll_year' => 2026,
            'status' => SalaryAdjustment::STATUS_APPROVED,
        ]);
        SalaryAdjustment::create([
            'user_id' => $employee->id,
            'adjustment_type' => SalaryAdjustment::TYPE_ADVANCE_RECOVERY,
            'amount' => 125,
            'adjustment_date' => '2026-04-15',
            'payroll_month' => 4,
            'payroll_year' => 2026,
            'status' => SalaryAdjustment::STATUS_APPROVED,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/hr/salary-adjustments-payroll-summary?year=2026&month=4')
            ->assertOk()
            ->assertJsonPath('by_type.partial_pay', 300)
            ->assertJsonPath('by_type.advance_recovery', 125);
    }

    private function administrator(): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function insertLegacySummary(User $employee, int $year, int $month): int
    {
        return DB::table('salary_adjustments')->insertGetId([
            'user_id' => $employee->id,
            'year' => $year,
            'month' => $month,
            'base_salary' => 5000,
            'total_deductions' => 999,
            'total_rewards' => 999,
            'net_adjustment' => 0,
            'final_salary' => 5000,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
