<?php

namespace Tests\Feature\Lead;

use App\Modules\Lead\Models\Lead;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeadSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_source_requires_and_stores_sales_name_in_metadata(): void
    {
        $user = $this->userWithPermissions(['manage_leads']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/leads', [
                'title' => 'Enterprise CRM',
                'source' => 'sales',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sales_name');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/leads', [
                'title' => 'Enterprise CRM',
                'source' => 'sales',
                'sales_name' => 'Omar Sales',
                'account_manager_id' => $user->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'sales');

        $lead = Lead::query()->firstOrFail();

        $this->assertSame('Omar Sales', $lead->metadata['sales_name'] ?? null);
        $this->assertSame($user->id, $lead->assigned_to);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
