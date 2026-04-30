<?php

namespace Tests\Feature\SOP;

use App\Modules\SOP\Actions\GetTodayDirectiveForUserAction;
use App\Modules\SOP\Models\Directive;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GetTodayDirectiveForUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_latest_visible_directive_for_user_permissions(): void
    {
        Permission::firstOrCreate(['name' => 'view_morning_sop', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_finance_sop', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('view_morning_sop');

        Directive::create([
            'title' => 'Finance SOP',
            'description' => 'Hidden finance workflow',
            'phases' => [['name' => 'Planning', 'minutes' => 30]],
            'target_permissions' => ['view_finance_sop'],
            'is_recurring' => true,
            'created_by' => $user->id,
            'created_at' => now()->subMinutes(10),
        ]);

        $visibleDirective = Directive::create([
            'title' => 'Morning SOP',
            'description' => 'Visible workflow',
            'phases' => [['name' => 'Silent Hours', 'starts_at' => '09:00']],
            'target_permissions' => ['view_morning_sop'],
            'is_recurring' => true,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        $directive = app(GetTodayDirectiveForUserAction::class)->execute($user);

        $this->assertNotNull($directive);
        $this->assertTrue($visibleDirective->is($directive));
    }

    public function test_it_returns_null_when_user_lacks_required_permissions(): void
    {
        Permission::firstOrCreate(['name' => 'view_finance_sop', 'guard_name' => 'web']);

        $creator = User::factory()->create();
        $user = User::factory()->create();

        Directive::create([
            'title' => 'Finance SOP',
            'description' => 'Hidden finance workflow',
            'phases' => [['name' => 'Planning', 'minutes' => 30]],
            'target_permissions' => ['view_finance_sop'],
            'is_recurring' => true,
            'created_by' => $creator->id,
        ]);

        $directive = app(GetTodayDirectiveForUserAction::class)->execute($user);

        $this->assertNull($directive);
    }
}
