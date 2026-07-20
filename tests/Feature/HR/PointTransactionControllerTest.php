<?php

namespace Tests\Feature\HR;

use App\Enums\PointTransactionType;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PointTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manually_adjust_employee_points(): void
    {
        $admin = $this->userWithPermissions(['award_points']);
        $employee = User::factory()->create(['total_points' => 7]);
        $this->employeeProfile($employee, 8000);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/hr/point-adjustments', [
                'userId' => $employee->id,
                'points' => 5,
                'reason' => 'Manual delivery bonus',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.points', 5)
            ->assertJsonPath('data.reason', 'Manual delivery bonus')
            ->assertJsonPath('data.balanceAfter', 12);

        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $employee->id,
            'points' => 5,
            'type' => 'manual_bonus',
            'reason' => 'Manual delivery bonus',
            'balance_after' => 12,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'total_points' => 12,
        ]);
    }

    public function test_manual_point_deduction_requires_deduct_points_permission(): void
    {
        $admin = $this->userWithPermissions(['award_points']);
        $employee = User::factory()->create(['total_points' => 7]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/hr/point-adjustments', [
                'userId' => $employee->id,
                'points' => -3,
                'reason' => 'Manual correction',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $employee->id,
            'points' => -3,
        ]);
    }

    public function test_point_manager_cannot_award_compensation_bearing_points_to_self(): void
    {
        $manager = $this->userWithPermissions(['manage_points']);
        $this->employeeProfile($manager, 8000);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/hr/point-adjustments', [
                'userId' => $manager->id,
                'points' => 5,
                'reason' => 'Self award',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('point_transactions', 0);
        $this->assertSame(0, $manager->fresh()->total_points);
    }

    public function test_positive_adjustments_respect_salary_derived_event_and_monthly_bounds(): void
    {
        Setting::setValue('gamification_point_value', 50);
        $manager = $this->userWithPermissions(['award_points']);
        $employee = User::factory()->create(['total_points' => 80]);
        $this->employeeProfile($employee, 5000);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/hr/point-adjustments', [
                'userId' => $employee->id,
                'points' => 101,
                'reason' => 'Over per-event bound',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('point_transactions', 0);
        $this->assertSame(80, $employee->fresh()->total_points);

        PointTransaction::create([
            'user_id' => $employee->id,
            'points' => 80,
            'type' => PointTransactionType::TASK_ON_TIME,
            'reason' => 'Existing monthly earnings',
            'balance_after' => 80,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/hr/point-adjustments', [
                'userId' => $employee->id,
                'points' => 21,
                'reason' => 'Over cumulative monthly bound',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('point_transactions', 1);
        $this->assertSame(80, $employee->fresh()->total_points);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/hr/point-adjustments', [
                'userId' => $employee->id,
                'points' => 20,
                'reason' => 'Within remaining monthly bound',
            ])
            ->assertCreated()
            ->assertJsonPath('data.balanceAfter', 100);

        $this->assertSame(100, $employee->fresh()->total_points);
    }

    public function test_manual_deduction_cannot_create_a_negative_balance(): void
    {
        $manager = $this->userWithPermissions(['deduct_points']);
        $employee = User::factory()->create(['total_points' => 7]);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/hr/point-adjustments', [
                'userId' => $employee->id,
                'points' => -8,
                'reason' => 'Exceeds available balance',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('point_transactions', 0);
        $this->assertSame(7, $employee->fresh()->total_points);
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

    private function employeeProfile(User $user, float $baseSalary): EmployeeProfile
    {
        return EmployeeProfile::create([
            'user_id' => $user->id,
            'base_salary' => $baseSalary,
            'hourly_rate' => 50,
            'join_date' => '2025-01-01',
        ]);
    }
}
