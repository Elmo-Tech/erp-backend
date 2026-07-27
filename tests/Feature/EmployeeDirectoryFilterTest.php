<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeDirectoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_employee_filter_returns_active_employees_for_pipeline_assignment(): void
    {
        $employeeManager = User::factory()->create([
            'is_employee' => true,
            'is_active' => true,
        ]);
        $employeeManager->givePermissionTo(Permission::findOrCreate('view_employees', 'web'));
        $activeEmployees = User::factory()->count(2)->create([
            'is_employee' => true,
            'is_active' => true,
        ])->push($employeeManager);
        User::factory()->create([
            'is_employee' => true,
            'is_active' => false,
        ]);

        $response = $this->actingAs($employeeManager, 'sanctum')
            ->getJson('/api/v1/employees?filter[isActive]=true&perPage=100')
            ->assertOk()
            ->assertJsonCount(3, 'data.employees');

        $this->assertSame(
            $activeEmployees->pluck('id')->sort()->values()->all(),
            collect($response->json('data.employees'))->pluck('userId')->sort()->values()->all()
        );
    }

    public function test_employee_search_filter_matches_the_documented_nested_query(): void
    {
        $employeeManager = User::factory()->create(['name' => 'Directory Manager']);
        $employeeManager->givePermissionTo(Permission::findOrCreate('view_employees', 'web'));
        $matchingEmployee = User::factory()->create(['name' => 'Mona Hassan']);
        User::factory()->create(['name' => 'Omar Ali']);

        $this->actingAs($employeeManager, 'sanctum')
            ->getJson('/api/v1/employees?filter[search]=Mona')
            ->assertOk()
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.userId', $matchingEmployee->id);
    }
}
