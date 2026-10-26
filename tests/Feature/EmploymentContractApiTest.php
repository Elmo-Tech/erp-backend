<?php

namespace Tests\Feature;

use App\Models\EmploymentContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmploymentContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_end_date_is_derived_with_month_end_safe_arithmetic(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/employment-contracts", $this->contractPayload([
                'startDate' => '2026-01-31',
                'durationMonths' => 1,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.startDate', '2026-01-31')
            ->assertJsonPath('data.endDate', '2026-02-27')
            ->assertJsonPath('data.terminatedOn', null);

        $contract = EmploymentContract::findOrFail($response->json('data.id'));

        $this->assertSame('2026-01-31', $contract->start_date->toDateString());
        $this->assertSame('2026-02-27', $contract->end_date->toDateString());
        $this->assertSame(1, $contract->duration_months);
    }

    public function test_active_contract_replaces_prior_active_contract_and_history_is_retained(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true]);

        $firstId = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/employment-contracts", $this->contractPayload([
                'startDate' => '2026-01-01',
                'status' => EmploymentContract::STATUS_ACTIVE,
            ]))
            ->assertCreated()
            ->json('data.id');

        $secondId = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/employment-contracts", $this->contractPayload([
                'startDate' => '2026-06-01',
                'status' => EmploymentContract::STATUS_DRAFT,
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/employment-contracts/{$secondId}", [
                'status' => EmploymentContract::STATUS_ACTIVE,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $secondId)
            ->assertJsonPath('data.status', EmploymentContract::STATUS_ACTIVE)
            ->assertJsonPath('data.isCurrent', true);

        $this->assertDatabaseHas('employment_contracts', [
            'id' => $firstId,
            'status' => EmploymentContract::STATUS_SUPERSEDED,
        ]);
        $this->assertDatabaseHas('employment_contracts', [
            'id' => $secondId,
            'status' => EmploymentContract::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/employment-contracts")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $secondId)
            ->assertJsonPath('data.0.status', EmploymentContract::STATUS_ACTIVE)
            ->assertJsonPath('data.0.isCurrent', true)
            ->assertJsonPath('data.1.id', $firstId)
            ->assertJsonPath('data.1.status', EmploymentContract::STATUS_SUPERSEDED)
            ->assertJsonPath('data.1.isCurrent', false);
    }

    public function test_contract_endpoints_require_employee_management_permission_and_mutation_target_authorization(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $contract = EmploymentContract::create($this->contractAttributes($employee));
        $unprivileged = User::factory()->create();

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/employment-contracts")
            ->assertForbidden();
        $this->actingAs($unprivileged, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/employment-contracts", $this->contractPayload())
            ->assertForbidden();
        $this->actingAs($unprivileged, 'sanctum')
            ->putJson("/api/v1/employment-contracts/{$contract->id}", ['status' => EmploymentContract::STATUS_DRAFT])
            ->assertForbidden();

        $manager = $this->employeeManager();
        $peerManager = User::factory()->create(['is_employee' => true]);
        $peerManager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));
        $peerContract = EmploymentContract::create($this->contractAttributes($peerManager));

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$peerManager->id}/employment-contracts", $this->contractPayload())
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/employment-contracts/{$peerContract->id}", ['status' => EmploymentContract::STATUS_DRAFT])
            ->assertForbidden();
    }

    public function test_history_returns_not_found_for_a_non_employee_target(): void
    {
        $manager = $this->employeeManager();
        $client = User::factory()->create(['is_employee' => false]);
        $client->assignRole(Role::findOrCreate('client', 'web'));

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$client->id}/employment-contracts")
            ->assertNotFound();
    }

    public function test_contract_validation_and_resource_data_use_the_hr_api_shape(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create([
            'is_employee' => true,
            'salary' => 1234,
            'work_hours_per_day' => 6,
            'work_start_time' => '08:00:00',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/employment-contracts", $this->contractPayload([
                'durationMonths' => 0,
                'workDays' => [],
                'endDate' => '2099-01-01',
                'status' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['durationMonths', 'workDays', 'endDate', 'status']);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/employment-contracts", $this->contractPayload([
                'baseSalary' => 5000.5,
                'workHoursPerDay' => 7.5,
                'notes' => 'Initial part-time contract.',
            ]))
            ->assertCreated()
            ->assertJsonStructure(['data' => [
                'id',
                'employeeId',
                'employmentType',
                'baseSalary',
                'startDate',
                'endDate',
                'terminatedOn',
                'durationMonths',
                'workDays',
                'workHoursPerDay',
                'workStartTime',
                'status',
                'notes',
                'isCurrent',
                'createdAt',
                'updatedAt',
            ]])
            ->assertJsonPath('data.employeeId', $employee->id)
            ->assertJsonPath('data.employmentType', 'full_time')
            ->assertJsonPath('data.baseSalary', 5000.5)
            ->assertJsonPath('data.workDays', ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'])
            ->assertJsonPath('data.workHoursPerDay', 7.5)
            ->assertJsonPath('data.status', EmploymentContract::STATUS_DRAFT)
            ->assertJsonPath('data.notes', 'Initial part-time contract.')
            ->assertJsonPath('data.isCurrent', false);

        $this->assertSame('1234.00', $employee->fresh()->salary);
        $this->assertSame(6.0, $employee->fresh()->work_hours_per_day);
        $this->assertSame('08:00:00', $employee->fresh()->work_start_time);

        $otherEmployee = User::factory()->create(['is_employee' => true]);

        $this->actingAs($manager, 'sanctum')
            ->putJson('/api/v1/employment-contracts/'.$response->json('data.id'), [
                'employeeId' => $otherEmployee->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employeeId', 'status']);

        $this->assertDatabaseHas('employment_contracts', [
            'id' => $response->json('data.id'),
            'employee_id' => $employee->id,
        ]);
    }

    public function test_terminated_contracts_require_a_valid_actual_termination_date_without_changing_the_derived_end_date(): void
    {
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/employment-contracts", $this->contractPayload([
                'status' => EmploymentContract::STATUS_TERMINATED,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('terminatedOn');

        $contractId = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/employment-contracts", $this->contractPayload())
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/employment-contracts/{$contractId}", [
                'status' => EmploymentContract::STATUS_TERMINATED,
                'terminatedOn' => '2025-12-31',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('terminatedOn');
        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/employment-contracts/{$contractId}", [
                'status' => EmploymentContract::STATUS_TERMINATED,
                'terminatedOn' => '2026-01-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.terminatedOn', '2026-01-20')
            ->assertJsonPath('data.endDate', '2026-12-31');

        $contract = EmploymentContract::findOrFail($contractId);

        $this->assertSame(EmploymentContract::STATUS_TERMINATED, $contract->status);
        $this->assertSame('2026-01-20', $contract->terminated_on?->toDateString());
        $this->assertSame('2026-12-31', $contract->end_date?->toDateString());
    }

    public function test_salary_permission_updates_contract_and_employee_together_but_read_only_cannot_write(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $contract = EmploymentContract::create([...$this->contractAttributes($employee), 'status' => EmploymentContract::STATUS_ACTIVE]);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('view_employee_salary', 'web'));
        $this->actingAs($viewer, 'sanctum')->putJson('/api/v1/employees/'.$employee->id.'/salary', ['baseSalary' => 12000])->assertForbidden();
        $editor = User::factory()->create();
        $editor->givePermissionTo(Permission::findOrCreate('manage_employee_salary', 'web'));
        $this->actingAs($editor, 'sanctum')->putJson('/api/v1/employees/'.$employee->id.'/salary', ['baseSalary' => 12000])->assertOk();
        $this->assertEquals(12000, $employee->fresh()->salary);
        $this->assertEquals(12000, $contract->fresh()->base_salary);
        $this->putJson('/api/v1/employees/'.$employee->id, ['name' => 'Not authorized'])->assertForbidden();
    }

    private function employeeManager(): User
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));

        return $manager;
    }

    private function contractPayload(array $overrides = []): array
    {
        return array_merge([
            'employmentType' => 'full_time',
            'baseSalary' => 5000,
            'startDate' => '2026-01-01',
            'durationMonths' => 12,
            'workDays' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'workHoursPerDay' => 8,
            'workStartTime' => '09:00',
            'status' => EmploymentContract::STATUS_DRAFT,
            'notes' => null,
        ], $overrides);
    }

    private function contractAttributes(User $employee): array
    {
        return [
            'employee_id' => $employee->id,
            'employment_type' => 'full_time',
            'base_salary' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'duration_months' => 12,
            'work_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'work_hours_per_day' => 8,
            'work_start_time' => '09:00:00',
            'status' => EmploymentContract::STATUS_DRAFT,
        ];
    }
}
