<?php

namespace Tests\Feature\HR;

use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PointPermissionParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_manage_user_points_alias_cannot_authorize_adjustments(): void
    {
        $operator = User::factory()->create();
        $operator->givePermissionTo(Permission::findOrCreate('manage_user_points', 'web'));
        $target = User::factory()->create();

        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/hr/point-adjustments', [
                'userId' => $target->id,
                'points' => 1,
                'reason' => 'Ghost permission must not authorize writes',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('point_transactions', 0);
    }
}
