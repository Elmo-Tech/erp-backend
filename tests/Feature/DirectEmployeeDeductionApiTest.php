<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\EmployeeDeduction;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DirectEmployeeDeductionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_deduction_endpoints_require_employee_management_and_respect_target_hierarchy(): void
    {
        config(['simplification.enabled' => true]);

        $manager = $this->employeeManager();
        $employee = User::factory()->create();
        $peerManager = $this->employeeManager();
        $unprivileged = User::factory()->create();
        $deductionType = $this->deductionType();

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson('/api/v1/deduction-types/active')
            ->assertForbidden();
        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/deductions?year=2026&month=5")
            ->assertForbidden();

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/deduction-types/active')
            ->assertOk()
            ->assertJsonPath('data.0.id', $deductionType->id)
            ->assertJsonPath('data.0.requiresApproval', true);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$peerManager->id}/deductions", $this->deductionPayload($deductionType))
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$peerManager->id}/deductions?year=2026&month=5")
            ->assertForbidden();

        $peerDeduction = EmployeeDeduction::forceCreate([
            'user_id' => $peerManager->id,
            'deduction_type_id' => $deductionType->id,
            'deduction_date' => '2026-05-15',
            'amount' => 125.50,
            'reason' => 'Peer manager deduction',
            'status' => EmployeeDeduction::STATUS_PENDING,
            'source' => EmployeeDeduction::SOURCE_DIRECT_MANUAL,
            'deductible_type' => DeductionType::class,
            'deductible_id' => $deductionType->id,
            'created_by' => $peerManager->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-deductions/{$peerDeduction->id}/approve")
            ->assertForbidden();
    }

    public function test_direct_deduction_rejects_inactive_types_invalid_amounts_and_attendance_links(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create();
        $inactiveType = $this->deductionType(['is_active' => false]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/deductions", $this->deductionPayload($inactiveType, [
                'amount' => '10.123',
                'attendanceId' => 1,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deductionTypeId', 'amount', 'attendanceId']);
    }

    public function test_direct_deduction_create_and_history_only_include_direct_records_with_summary(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create();
        $deductionType = $this->deductionType();

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/deductions", $this->deductionPayload($deductionType))
            ->assertCreated()
            ->assertJsonStructure(['data' => [
                'id',
                'employeeId',
                'deductionType' => ['id', 'name'],
                'deductionDate',
                'amount',
                'status',
                'reason',
                'approvedBy',
                'approvedAt',
                'rejectionReason',
                'salaryAdjustmentId',
                'createdAt',
            ]])
            ->assertJsonPath('data.employeeId', $employee->id)
            ->assertJsonPath('data.deductionType.id', $deductionType->id)
            ->assertJsonPath('data.status', EmployeeDeduction::STATUS_PENDING);

        $directDeductionId = $response->json('data.id');

        $this->assertDatabaseHas('employee_deductions', [
            'id' => $directDeductionId,
            'user_id' => $employee->id,
            'source' => EmployeeDeduction::SOURCE_DIRECT_MANUAL,
            'attendance_id' => null,
            'late_minutes' => null,
            'deductible_type' => DeductionType::class,
            'deductible_id' => $deductionType->id,
        ]);

        EmployeeDeduction::forceCreate([
            'user_id' => $employee->id,
            'deduction_type_id' => $deductionType->id,
            'deduction_date' => '2026-05-15',
            'amount' => 99.99,
            'reason' => 'Legacy attendance-linked deduction',
            'status' => EmployeeDeduction::STATUS_APPROVED,
            'deductible_type' => DeductionType::class,
            'deductible_id' => $deductionType->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/deductions?year=2026&month=5")
            ->assertOk()
            ->assertJsonCount(1, 'data.deductions')
            ->assertJsonPath('data.deductions.0.id', $directDeductionId)
            ->assertJsonPath('data.summary.approvedAmount', 0)
            ->assertJsonPath('data.summary.pendingCount', 1)
            ->assertJsonPath('data.summary.approvedCount', 0);
    }

    public function test_approving_a_direct_deduction_creates_one_linked_approved_adjustment(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create();
        $deductionType = $this->deductionType();
        $deductionId = $this->createDirectDeduction($manager, $employee, $deductionType);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-deductions/{$deductionId}/approve", [
                'reviewNote' => 'Verified against the manager request.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeDeduction::STATUS_APPROVED)
            ->assertJsonPath('data.approvedBy', $manager->id);

        $adjustmentId = $response->json('data.salaryAdjustmentId');
        $this->assertNotNull($adjustmentId);
        $this->assertDatabaseHas('salary_adjustment_transactions', [
            'id' => $adjustmentId,
            'user_id' => $employee->id,
            'adjustment_type' => SalaryAdjustment::TYPE_DEDUCTION,
            'reference_type' => EmployeeDeduction::class,
            'reference_id' => $deductionId,
            'amount' => 125.50,
            'reason' => 'Manual equipment recovery.',
            'payroll_month' => 5,
            'payroll_year' => 2026,
            'status' => SalaryAdjustment::STATUS_APPROVED,
            'approved_by' => $manager->id,
            'notes' => 'Verified against the manager request.',
        ]);
        $this->assertSame('2026-05-15', SalaryAdjustment::findOrFail($adjustmentId)->adjustment_date->toDateString());

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-deductions/{$deductionId}/approve", [
                'reviewNote' => 'Attempted duplicate approval.',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('salary_adjustment_transactions', 1);
    }

    public function test_rejection_and_cancellation_are_pending_only_and_do_not_create_adjustments(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create();
        $deductionType = $this->deductionType();
        $rejectedId = $this->createDirectDeduction($manager, $employee, $deductionType);
        $cancelledId = $this->createDirectDeduction($manager, $employee, $deductionType, [
            'deductionDate' => '2026-05-16',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-deductions/{$rejectedId}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reviewNote']);
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-deductions/{$rejectedId}/reject", [
                'reviewNote' => 'Supporting documentation was not provided.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeDeduction::STATUS_REJECTED)
            ->assertJsonPath('data.rejectionReason', 'Supporting documentation was not provided.');
        $this->assertDatabaseHas('employee_deductions', [
            'id' => $rejectedId,
            'approved_by' => $manager->id,
            'rejection_reason' => 'Supporting documentation was not provided.',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-deductions/{$cancelledId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeDeduction::STATUS_CANCELLED);
        $this->assertDatabaseHas('employee_deductions', [
            'id' => $cancelledId,
            'status' => EmployeeDeduction::STATUS_CANCELLED,
        ]);
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-deductions/{$cancelledId}/cancel")
            ->assertUnprocessable();

        $this->assertDatabaseCount('salary_adjustment_transactions', 0);
    }

    private function employeeManager(): User
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));

        return $manager;
    }

    private function deductionType(array $attributes = []): DeductionType
    {
        return DeductionType::create(array_merge([
            'name' => 'Manual recovery',
            'code' => 'MANUAL_RECOVERY',
            'calculation_type' => DeductionType::CALCULATION_FIXED,
            'amount' => 125.50,
            'is_active' => true,
            'requires_approval' => true,
        ], $attributes));
    }

    private function deductionPayload(DeductionType $deductionType, array $overrides = []): array
    {
        return array_merge([
            'deductionTypeId' => $deductionType->id,
            'deductionDate' => '2026-05-15',
            'amount' => 125.50,
            'reason' => 'Manual equipment recovery.',
        ], $overrides);
    }

    private function createDirectDeduction(User $manager, User $employee, DeductionType $deductionType, array $overrides = []): int
    {
        return $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/deductions", $this->deductionPayload($deductionType, $overrides))
            ->assertCreated()
            ->json('data.id');
    }
}
