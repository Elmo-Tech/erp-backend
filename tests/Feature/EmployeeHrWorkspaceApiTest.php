<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeOvertimeRequest;
use App\Models\EmployeePermissionAllowance;
use App\Models\EmploymentContract;
use App\Models\LeaveType;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeHrWorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrative_leave_is_approved_atomically_and_invalid_balance_leaves_no_pending_record(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create(['is_employee' => true]);
        $type = LeaveType::where('slug', 'annual')->firstOrFail();
        $type->update(['annual_allowance_days' => 1]);
        $payload = ['leaveTypeId' => $type->id, 'startDate' => '2026-10-12', 'endDate' => '2026-10-12'];
        $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/leaves", $payload)
            ->assertCreated()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.reviewedBy', $manager->id);
        $this->assertSame('1.00', EmployeeLeaveBalance::sole()->used_days);
        $payload['startDate'] = $payload['endDate'] = '2026-10-13';
        $this->postJson("/api/v1/employees/{$employee->id}/leaves", $payload)->assertUnprocessable();
        $this->assertDatabaseCount('employee_leaves', 1);
        $this->assertSame('1.00', EmployeeLeaveBalance::sole()->used_days);
    }

    public function test_paid_choice_is_required_at_registration_and_cancellation_releases_allowance(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create(['is_employee' => true]);
        $payload = ['permissionDate' => '2026-10-12', 'startTime' => '09:00', 'endTime' => '10:00'];
        $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/short-permissions", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('compensationType');
        $this->assertDatabaseCount('employee_short_permissions', 0);
        $id = $this->postJson("/api/v1/employees/{$employee->id}/short-permissions", $payload + ['compensationType' => 'paid'])
            ->assertCreated()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.isPaid', true)->json('data.id');
        $this->assertSame(60, EmployeePermissionAllowance::sole()->used_paid_minutes);
        $this->postJson("/api/v1/employee-short-permissions/{$id}/cancel")->assertOk();
        $this->assertSame(0, EmployeePermissionAllowance::sole()->used_paid_minutes);
        $this->postJson("/api/v1/employees/{$employee->id}/short-permissions", $payload + ['compensationType' => 'unpaid'])
            ->assertCreated()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.isPaid', false);
        $this->assertSame(0, EmployeePermissionAllowance::sole()->used_paid_minutes);
    }

    public function test_contract_is_the_source_of_salary_monthly_target_and_fractional_work_schedule(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => null]);
        $id = $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/employment-contracts", [
            'employmentType' => 'part_time', 'baseSalary' => 6000, 'requiredMonthlyHours' => 80,
            'startDate' => '2026-10-01', 'durationMonths' => 12, 'workDays' => ['sunday', 'monday'],
            'workHoursPerDay' => 4.5, 'workStartTime' => '09:00', 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.requiredMonthlyHours', 80)->json('data.id');
        $employee->refresh();
        $this->assertSame('6000.00', $employee->salary);
        $this->assertSame(4.5, $employee->work_hours_per_day);
        $this->assertSame('13:30:00', $employee->expected_clock_out);
        $this->putJson("/api/v1/employment-contracts/{$id}", ['status' => 'active', 'baseSalary' => 6500])->assertOk();
        $this->assertNull($employee->fresh()->hourly_rate);
        $this->assertSame('6500.00', $employee->fresh()->salary);
    }

    public function test_contract_and_employee_document_files_upload_and_download(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $employee = User::factory()->create(['is_employee' => true]);
        $contract = EmploymentContract::create([
            'employee_id' => $employee->id, 'employment_type' => 'full_time', 'base_salary' => 6000,
            'start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'duration_months' => 12,
            'work_days' => ['monday'], 'work_hours_per_day' => 8, 'work_start_time' => '09:00', 'status' => 'active',
        ]);
        $this->actingAs($manager, 'sanctum')->post("/api/v1/employment-contracts/{$contract->id}/file", ['file' => UploadedFile::fake()->image('contract.png')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.hasFile', true);
        $this->get("/api/v1/employment-contracts/{$contract->id}/file")->assertOk()->assertDownload('contract.png');
        $documentId = $this->post("/api/v1/employees/{$employee->id}/documents", [
            'name' => 'Employee ID', 'documentType' => 'Identity', 'file' => UploadedFile::fake()->image('identity.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->get("/api/v1/employee-documents/{$documentId}/download")->assertOk()->assertDownload('identity.jpg');
    }

    public function test_super_admin_can_view_own_advances_and_register_a_one_step_employee_withdrawal(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 6000]);
        $this->actingAs($manager, 'sanctum')->getJson("/api/v1/employees/{$manager->id}/advances?year=2026&month=10")->assertOk();
        $id = $this->postJson("/api/v1/employees/{$employee->id}/advances", ['requestedAmount' => 1000, 'reason' => 'Cash advance'])->assertCreated()->assertJsonPath('data.status', 'settled')->json('data.id');
        $this->postJson("/api/v1/employee-advances/{$id}/approve")->assertUnprocessable();
        $this->getJson("/api/v1/employee-advances/{$id}")->assertOk()->assertJsonPath('data.outstandingAmount', 0)->assertJsonPath('data.disbursedAmount', 1000);
    }

    public function test_deduction_types_can_be_managed_in_simplified_mode_and_used_for_direct_deductions(): void
    {
        config()->set('simplification.enabled', true);
        $manager = $this->manager();
        $employee = User::factory()->create(['is_employee' => true]);
        $typeId = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/hr/deduction-types', [
            'name' => 'Direct deduction', 'code' => 'direct-test', 'category' => 'other', 'is_active' => true,
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/hr/deduction-types')->assertOk();
        $this->getJson('/api/v1/deduction-types/active')->assertOk();
        $id = $this->postJson("/api/v1/employees/{$employee->id}/deductions", [
            'deductionTypeId' => $typeId, 'deductionDate' => '2026-10-12', 'amount' => 100, 'reason' => 'Recorded deduction',
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/employee-deductions/{$id}/approve")->assertUnprocessable();
        $this->assertDatabaseHas('salary_adjustment_transactions', ['user_id' => $employee->id, 'amount' => 100, 'status' => 'approved']);
    }

    public function test_overview_excludes_pending_cancelled_other_employee_and_other_month_amounts(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 6000]);
        foreach ([['approved', 100, $employee->id, 10], ['processed', 50, $employee->id, 10], ['pending', 200, $employee->id, 10], ['cancelled', 300, $employee->id, 10], ['approved', 400, $manager->id, 10], ['approved', 500, $employee->id, 9]] as [$status, $amount, $userId, $month]) {
            SalaryAdjustment::create(['user_id' => $userId, 'adjustment_type' => 'deduction', 'amount' => $amount, 'reason' => 'Deduction', 'adjustment_date' => '2026-10-12', 'payroll_month' => $month, 'payroll_year' => 2026, 'status' => $status]);
        }
        Attendance::create(['user_id' => $employee->id, 'date' => '2026-10-12', 'clock_in' => '2026-10-12 09:20', 'late_minutes' => 20]);
        $this->actingAs($manager, 'sanctum')->getJson("/api/v1/employees/{$employee->id}/overview?month=2026-10")
            ->assertOk()->assertJsonPath('data.salary', 6000)->assertJsonPath('data.deductions', 150)
            ->assertJsonPath('data.attendance.days', 1)->assertJsonPath('data.attendance.lateMinutes', 20);
        $this->actingAs($employee, 'sanctum')->getJson("/api/v1/employees/{$manager->id}/overview?month=2026-10")->assertForbidden();
    }

    public function test_overtime_registration_approves_once_using_the_salary_derived_rate(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create(['is_employee' => true, 'salary' => 6000, 'hourly_rate' => 0, 'work_start_time' => '09:00', 'work_hours_per_day' => 8]);
        $payload = ['overtimeDate' => today()->addDays(2)->toDateString(), 'startTime' => '18:00', 'endTime' => '19:00', 'reason' => 'Delivery'];
        $id = $this->actingAs($manager, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/overtime-requests", $payload)->assertCreated()
            ->assertJsonPath('data.status', 'approved')->assertJsonPath('data.hourlyRate', 25)->json('data.id');
        $this->assertDatabaseCount('salary_adjustment_transactions', 0);
        $this->postJson("/api/v1/employee-overtime-requests/{$id}/approve")->assertUnprocessable();
        $this->postJson("/api/v1/employee-overtime-requests/{$id}/cancel")->assertOk();
        $this->assertSame(EmployeeOvertimeRequest::STATUS_CANCELLED, EmployeeOvertimeRequest::findOrFail($id)->status);
    }

    private function manager(): User
    {
        $manager = User::factory()->create(['is_employee' => true]);
        $manager->assignRole(Role::findOrCreate('super-admin', 'web'));
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'), Permission::findOrCreate('manage_protected_users', 'web'));

        return $manager;
    }
}
