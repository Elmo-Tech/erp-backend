<?php

namespace Tests\Feature\SOP;

use App\Modules\SOP\Models\Directive;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SOPControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_submit_checkin_for_directive_they_cannot_view(): void
    {
        Permission::firstOrCreate(['name' => 'view_finance_sop', 'guard_name' => 'web']);

        $creator = User::factory()->create();
        $user = User::factory()->create();

        $directive = Directive::create([
            'title' => 'Finance SOP',
            'description' => 'Hidden workflow',
            'phases' => [['name' => 'Planning']],
            'target_permissions' => ['view_finance_sop'],
            'is_recurring' => true,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sop/daily-checkins', [
                'directive_id' => $directive->id,
                'focus_items' => ['One', 'Two', 'Three'],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('daily_checkins', 0);
    }
}
