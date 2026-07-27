<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeSalaryEditVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_manager_receives_edit_salary_values_in_simplified_mode(): void
    {
        config()->set('simplification.enabled', true);

        $employeeManager = User::factory()->create(['name' => 'ZZZ Manager']);
        $employeeManager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));
        $employee = User::factory()->create([
            'name' => 'AAA Target Employee',
            'is_employee' => true,
            'salary' => 26000,
            'work_hours_per_day' => 8,
            'internal_hourly_cost' => 150,
        ]);

        $this->actingAs($employeeManager, 'sanctum')
            ->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonPath('data.employees.0.userId', $employee->id)
            ->assertJsonPath('data.employees.0.salary', 26000)
            ->assertJsonPath('data.employees.0.monthlyWorkHours', 173.33)
            ->assertJsonPath('data.employees.0.internalHourlyCost', 150);
    }

    public function test_user_without_salary_permission_cannot_receive_edit_salary_values(): void
    {
        $viewer = User::factory()->create(['name' => 'ZZZ Viewer']);
        $employee = User::factory()->create([
            'name' => 'AAA Protected Employee',
            'is_employee' => true,
            'salary' => 26000,
            'work_hours_per_day' => 8,
            'internal_hourly_cost' => 150,
        ]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonPath('data.employees.0.userId', $employee->id)
            ->assertJsonPath('data.employees.0.salary', null)
            ->assertJsonPath('data.employees.0.internalHourlyCost', null);
    }
}
