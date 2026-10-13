<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeDocument;
use App\Models\EmploymentContract;
use App\Models\EvaluationPeriod;
use App\Models\LeaveType;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\SalaryAdjustment;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\HR\EmployeeSalaryPeriodService;
use App\Services\HR\WorkCommitmentService;
use App\Services\Performance\PerformanceEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeHrSimplificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_part_time_contract_requires_monthly_hours_and_removes_the_manual_hourly_rate(): void
    {
        $admin = $this->administrator();
        $employee = User::factory()->create(['is_employee' => true]);
        $payload = ['employmentType' => 'part_time', 'baseSalary' => 8000, 'startDate' => '2026-10-01', 'durationMonths' => 6,
            'workDays' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'], 'workHoursPerDay' => 4, 'workStartTime' => '09:00', 'status' => 'active'];
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/employment-contracts", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('requiredMonthlyHours');
        $id = $this->postJson("/api/v1/employees/{$employee->id}/employment-contracts", [...$payload, 'requiredMonthlyHours' => 80, 'hourlyRate' => 999])
            ->assertCreated()->assertJsonPath('data.requiredMonthlyHours', 80)->assertJsonMissingPath('data.hourlyRate')->json('data.id');
        $this->assertNull($employee->fresh()->hourly_rate);
        $this->assertEquals(100, $employee->fresh()->internal_hourly_cost);
        $this->putJson("/api/v1/employment-contracts/{$id}", ['status' => 'active', 'requiredMonthlyHours' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('requiredMonthlyHours');
        $this->putJson("/api/v1/employment-contracts/{$id}", ['status' => 'active', 'employmentType' => 'full_time'])
            ->assertOk()->assertJsonPath('data.requiredMonthlyHours', null);
    }

    public function test_admin_withdrawal_records_the_exact_time_and_deducts_once_in_the_correct_month(): void
    {
        Carbon::setTestNow('2026-10-13 12:00:00');
        $admin = $this->administrator();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 6200]);
        $this->contract($employee, ['start_date' => '2026-10-05']);
        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/advances", [
            'requestedAmount' => 1000, 'reason' => 'Personal withdrawal', 'withdrawalDate' => '2026-10-12T10:35:00+03:00',
        ])->assertCreated()->assertJsonPath('data.status', 'settled')->assertJsonPath('data.isSalaryWithdrawal', true)
            ->assertJsonPath('data.approvedBy', $admin->id)->assertJsonPath('data.disbursedAmount', 1000)->assertJsonPath('data.outstandingAmount', 0);
        $id = $response->json('data.id');
        $this->assertSame('2026-10-12 10:35:00', EmployeeAdvance::findOrFail($id)->disbursed_at->format('Y-m-d H:i:s'));
        $adjustment = SalaryAdjustment::sole();
        $this->assertEquals(-1000, $adjustment->signed_amount);
        $this->assertSame(10, $adjustment->payroll_month);
        $this->postJson("/api/v1/employee-advances/{$id}/approve")->assertUnprocessable();
        $this->postJson('/api/v1/employee-advance-settlements/'.$response->json('data.settlements.0.id').'/settle')->assertUnprocessable();
        $this->assertDatabaseCount('salary_adjustment_transactions', 1);
        $this->getJson("/api/v1/employees/{$employee->id}/overview?month=2026-10")->assertOk()
            ->assertJsonPath('data.salaryPeriod.grossSalary', 5400)->assertJsonPath('data.salaryPeriod.netSalary', 4400)
            ->assertJsonPath('data.salaryPeriod.serviceDays', 27)->assertJsonPath('data.advancesDisbursed', 1000);
    }

    public function test_direct_deduction_needs_no_type_and_admin_approval_creates_one_salary_transaction(): void
    {
        Carbon::setTestNow('2026-10-13 12:00:00');
        $admin = $this->administrator('admin');
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 5000]);
        $id = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/deductions", [
            'amount' => 200, 'reason' => 'Direct deduction', 'deductionDate' => '2026-10-09', 'occurredAt' => '2026-10-09T14:10:00+03:00',
        ])->assertCreated()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.approvedBy', $admin->id)->json('data.id');
        $this->postJson("/api/v1/employee-deductions/{$id}/approve")->assertUnprocessable();
        $this->assertDatabaseCount('salary_adjustment_transactions', 1);
        $this->assertEquals(-200, SalaryAdjustment::sole()->signed_amount);
    }

    public function test_employee_request_stays_pending_until_authorized_review_then_pays_and_deducts_in_one_step(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $id = $this->actingAs($employee, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/advances", ['requestedAmount' => 1000, 'reason' => 'Advance request'])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.disbursedAt', null)->json('data.id');
        $this->assertDatabaseCount('salary_adjustment_transactions', 0);
        $this->postJson("/api/v1/employee-advances/{$id}/approve")->assertForbidden();
        $this->actingAs($this->administrator(), 'sanctum')->postJson("/api/v1/employee-advances/{$id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'settled')->assertJsonPath('data.disbursedAmount', 1000);
        $this->assertDatabaseCount('salary_adjustment_transactions', 1);
    }

    public function test_hour_commitment_changes_productivity_and_retains_the_evaluation_snapshot(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $contract = $this->contract($employee, ['employment_type' => 'part_time', 'required_monthly_hours' => 80, 'start_date' => '2026-10-01']);
        $task = Task::factory()->create();
        TimeEntry::factory()->create(['user_id' => $employee->id, 'trackable_type' => Task::class, 'trackable_id' => $task->id, 'start_time' => '2026-10-02 09:00:00', 'end_time' => '2026-10-04 01:00:00', 'duration_minutes' => 2400]);
        $period = EvaluationPeriod::create(['name' => 'October', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'active']);
        $service = app(PerformanceEvaluationService::class);
        $evaluation = $service->calculateScores($service->createEvaluation($employee, $period));
        $this->assertEquals(11, $evaluation->productivity_total);
        $this->assertEquals(50, $evaluation->work_commitment_snapshot['completionPercentage']);
        $contract->update(['required_monthly_hours' => 160]);
        $this->assertEquals(80, $evaluation->fresh()->work_commitment_snapshot['requiredMonthlyHours']);
        $commitment = app(WorkCommitmentService::class)->summary($employee, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertEquals(25, $commitment['completionPercentage']);
    }

    public function test_proration_handles_leap_year_month_end_and_contract_renewals_without_double_counting(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $old = $this->contract($employee, ['base_salary' => 2900, 'start_date' => '2024-02-29', 'end_date' => '2025-02-28']);
        $salary = app(EmployeeSalaryPeriodService::class)->calculate($employee, Carbon::parse('2024-02-01'));
        $this->assertEquals(100, $salary['grossSalary']);
        $this->assertSame(1, $salary['serviceDays']);
        $old->update(['status' => 'superseded', 'start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'base_salary' => 3100]);
        $this->contract($employee, ['start_date' => '2026-10-16', 'base_salary' => 6200]);
        $salary = app(EmployeeSalaryPeriodService::class)->calculate($employee, Carbon::parse('2026-10-01'));
        $this->assertEquals(4700, $salary['grossSalary']);
        $this->assertSame(31, $salary['serviceDays']);
    }

    public function test_comprehensive_report_is_employee_scoped_includes_all_sections_and_exports_a_real_pdf(): void
    {
        Carbon::setTestNow('2026-10-13 12:00:00');
        $admin = $this->administrator();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 6200]);
        $other = User::factory()->create(['is_employee' => true]);
        $this->contract($employee, ['start_date' => '2026-10-05']);
        Attendance::create(['user_id' => $employee->id, 'date' => '2026-10-12', 'clock_in' => '2026-10-12 09:00:00', 'clock_out' => '2026-10-12 17:30:00', 'expected_clock_out' => '17:00:00']);
        Attendance::create(['user_id' => $other->id, 'date' => '2026-10-12', 'clock_in' => '2026-10-12 09:00:00']);
        EmployeeDocument::create(['employee_id' => $employee->id, 'name' => 'Private ID', 'document_type' => 'identity', 'original_filename' => 'id.pdf', 'disk' => 'local', 'path' => 'secret/private/id.pdf', 'mime_type' => 'application/pdf', 'size' => 100, 'uploaded_by' => $admin->id]);
        $url = "/api/v1/employees/{$employee->id}/hr-report?dateFrom=2026-10-01&dateTo=2026-10-31";
        $this->actingAs($other, 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs($admin, 'sanctum');
        $this->postJson("/api/v1/employees/{$employee->id}/advances", ['requestedAmount' => 1000, 'reason' => 'Salary withdrawal'])->assertCreated();
        $this->postJson("/api/v1/employees/{$employee->id}/deductions", ['amount' => 200, 'deductionDate' => '2026-10-12', 'reason' => 'Direct deduction'])->assertCreated();
        $this->postJson("/api/v1/employees/{$employee->id}/leaves", ['leaveTypeId' => LeaveType::where('slug', 'annual')->firstOrFail()->id, 'startDate' => '2026-10-11', 'endDate' => '2026-10-11'])->assertCreated();
        $this->postJson("/api/v1/employees/{$employee->id}/short-permissions", ['permissionDate' => '2026-10-13', 'startTime' => '10:00', 'endTime' => '11:00', 'compensationType' => 'paid'])->assertCreated();
        $task = Task::factory()->create(['title' => 'Employee report task']);
        $task->assignees()->create(['user_id' => $employee->id, 'role' => 'developer', 'is_primary' => true]);
        ProjectMember::create(['project_id' => $task->project_id, 'user_id' => $employee->id, 'role' => 'developer']);
        TimeEntry::factory()->create(['user_id' => $employee->id, 'trackable_type' => Task::class, 'trackable_id' => $task->id, 'start_time' => '2026-10-12 09:00:00', 'end_time' => '2026-10-12 10:00:00', 'duration_minutes' => 60]);
        TimeEntry::factory()->create(['user_id' => $employee->id, 'trackable_type' => Project::class, 'trackable_id' => $task->project_id, 'start_time' => '2026-10-12 11:00:00', 'end_time' => '2026-10-12 11:30:00', 'duration_minutes' => 30]);
        $response = $this->actingAs($admin, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.employee.id', $employee->id);
        $sections = collect($response->json('data.sections'))->keyBy('key');
        $this->assertCount(20, $sections);
        foreach (['contracts', 'attendance', 'leaves', 'leave-balances', 'permissions', 'permission-balances', 'overtime', 'advances', 'advance-recovery', 'deductions', 'late-deductions', 'salary-transactions', 'partial-pay', 'tasks', 'projects', 'time-entries', 'evaluations', 'supervision', 'documents'] as $key) {
            $this->assertTrue($sections->has($key), $key);
        }
        $this->assertCount(1, $sections['attendance']['rows']);
        $this->assertCount(1, $sections['documents']['rows']);
        foreach (['advances', 'deductions', 'leaves', 'leave-balances', 'permissions', 'permission-balances', 'advance-recovery', 'tasks', 'projects', 'time-entries'] as $key) {
            $this->assertNotEmpty($sections[$key]['rows'], $key);
        }
        $this->assertSame(90, $sections['attendance']['rows'][0]['worked_minutes']);
        $this->assertSame(30, $sections['attendance']['rows'][0]['unapproved_overtime_minutes']);
        $projectTitle = Project::withoutGlobalScopes()->findOrFail($task->project_id)->title;
        $this->assertSame($projectTitle, $sections['tasks']['rows'][0]['project.title']);
        $this->assertSame($projectTitle, $sections['projects']['rows'][0]['title']);
        $this->assertSame($projectTitle, $sections['time-entries']['rows'][0]['trackable.title']);
        $this->assertEquals(4200, $response->json('data.salaryPeriods.0.netSalary'));
        $this->assertStringNotContainsString('secret/private', $response->getContent());
        $pdf = $this->get($url.'&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertGreaterThan(5000, strlen($pdf->getContent()));
    }

    private function administrator(string $role = 'super-admin'): User
    {
        $admin = User::factory()->create(['is_employee' => true]);
        $admin->assignRole(Role::findOrCreate($role, 'web'));
        $admin->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));
        if ($role === 'super-admin') {
            $admin->givePermissionTo(Permission::findOrCreate('manage_protected_users', 'web'));
        }

        return $admin;
    }

    private function contract(User $employee, array $overrides = []): EmploymentContract
    {
        return EmploymentContract::create([...[
            'employee_id' => $employee->id, 'employment_type' => 'full_time', 'base_salary' => 6200,
            'start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'duration_months' => 12,
            'work_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'], 'work_hours_per_day' => 8,
            'work_start_time' => '09:00:00', 'status' => 'active',
        ], ...$overrides]);
    }
}
