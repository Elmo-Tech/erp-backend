<?php

namespace Tests\Feature\HR;

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
}
