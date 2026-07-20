<?php

namespace Tests\Feature\HR;

use App\Enums\PointTransactionType;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Services\PayrollCalculatorService;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollCalculatorConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_calculates_and_persists_inside_one_transaction(): void
    {
        $employee = $this->employeeWithProfile();
        $baselineTransactionLevel = DB::transactionLevel();
        $calculator = new class extends PayrollCalculatorService
        {
            public int $calculationTransactionLevel = -1;

            public function calculateForUser(User $user, int $month, int $year): array
            {
                $this->calculationTransactionLevel = DB::transactionLevel();

                return parent::calculateForUser($user, $month, $year);
            }
        };

        $calculator->generateForUser($employee, 6, 2026);

        $this->assertGreaterThan($baselineTransactionLevel, $calculator->calculationTransactionLevel);
    }

    public function test_regeneration_restores_the_soft_deleted_natural_key_instead_of_inserting_a_duplicate(): void
    {
        $employee = $this->employeeWithProfile();
        $existing = $this->draftPayslip($employee, 6, 2026);
        $existing->delete();

        $regenerated = app(PayrollCalculatorService::class)->generateForUser($employee, 6, 2026);

        $this->assertSame($existing->id, $regenerated->id);
        $this->assertNull($regenerated->deleted_at);
        $this->assertSame(1, Payslip::withTrashed()->count());
    }

    public function test_regeneration_is_idempotent_for_drafts_and_never_replaces_finalized_payslips(): void
    {
        $employee = $this->employeeWithProfile();
        $calculator = app(PayrollCalculatorService::class);
        $draft = $calculator->generateForUser($employee, 6, 2026);

        $employee->employeeProfile->update(['base_salary' => 9500]);
        $regenerated = $calculator->generateForUser($employee->fresh(), 6, 2026);

        $this->assertSame($draft->id, $regenerated->id);
        $this->assertSame('9500.00', $regenerated->base_salary);

        $regenerated->update(['status' => 'approved']);
        $employee->employeeProfile->update(['base_salary' => 12000]);
        $finalized = $calculator->generateForUser($employee->fresh(), 6, 2026);

        $this->assertSame($draft->id, $finalized->id);
        $this->assertSame('approved', $finalized->status);
        $this->assertSame('9500.00', $finalized->base_salary);
        $this->assertSame(1, Payslip::withTrashed()->count());
    }

    public function test_payroll_fails_closed_when_monthly_points_would_exceed_base_salary(): void
    {
        Setting::setValue('gamification_point_value', 50);
        $employee = $this->employeeWithProfile();
        PointTransaction::create([
            'user_id' => $employee->id,
            'points' => 161,
            'type' => PointTransactionType::TASK_ON_TIME,
            'reason' => 'Unbounded upstream points',
            'balance_after' => 161,
        ]);

        try {
            app(PayrollCalculatorService::class)->generateForUser($employee, now()->month, now()->year);
            $this->fail('Payroll generation should reject a points bonus above base salary.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('exceeds the employee base salary', $exception->getMessage());
        }

        $this->assertDatabaseCount('payslips', 0);
    }

    private function employeeWithProfile(): User
    {
        $employee = User::factory()->create(['is_active' => true]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'base_salary' => 8000,
            'hourly_rate' => 50,
            'join_date' => '2025-01-01',
        ]);

        return $employee->fresh('employeeProfile');
    }

    private function draftPayslip(User $employee, int $month, int $year): Payslip
    {
        return Payslip::create([
            'user_id' => $employee->id,
            'month' => $month,
            'year' => $year,
            'base_salary' => 8000,
            'points_earned' => 0,
            'points_bonus_amount' => 0,
            'lateness_minutes' => 0,
            'lateness_deduction_amount' => 0,
            'net_salary' => 8000,
            'status' => 'draft',
        ]);
    }
}
