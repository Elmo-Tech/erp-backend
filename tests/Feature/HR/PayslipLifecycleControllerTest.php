<?php

namespace Tests\Feature\HR;

use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Services\PayrollCalculatorService;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayslipLifecycleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalized_payslip_cannot_be_regenerated_or_deleted(): void
    {
        $manager = $this->userWithPermissions(['manage_payroll']);
        $employee = $this->employeeWithProfile();
        $payslip = app(PayrollCalculatorService::class)->generateForUser($employee, 6, 2026);

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/hr/payslips/{$payslip->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $employee->employeeProfile->update(['base_salary' => 12000]);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/hr/payslips/generate', [
                'user_id' => $employee->id,
                'month' => 6,
                'year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.baseSalary', 8000);

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/hr/payslips/{$payslip->id}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/hr/payslips/{$payslip->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('payslips', [
            'id' => $payslip->id,
            'status' => 'paid',
            'base_salary' => 8000,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
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
}
