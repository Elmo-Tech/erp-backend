<?php

namespace Tests\Feature\Dashboard;

use App\Enums\TaskStatus;
use App\Modules\Task\Models\Task;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionDrivenDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_only_sections_allowed_by_user_permissions(): void
    {
        Permission::firstOrCreate(['name' => 'view_personal_tasks', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_financial_stats', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('view_personal_tasks');

        Task::factory()->create([
            'assigned_to' => $user->id,
            'title' => 'Personal dashboard task',
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => now()->addDay(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/overview');

        $response->assertOk()
            ->assertJsonStructure([
                'personal_tasks' => [
                    'summary' => [
                        'total',
                        'todo',
                        'in_progress',
                        'review',
                        'testing',
                        'completed',
                    ],
                    'items',
                ],
                'blockers',
            ])
            ->assertJsonMissingPath('financial')
            ->assertJsonMissingPath('timeline')
            ->assertJsonMissingPath('team');
    }

    public function test_dashboard_returns_empty_object_without_dashboard_section_permissions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/overview');

        $response->assertOk()
            ->assertExactJson([]);
    }

    public function test_global_dashboard_sections_require_global_stats_permission(): void
    {
        Permission::firstOrCreate(['name' => 'view_financial_stats', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_global_stats', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('view_financial_stats');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/overview');

        $response->assertOk()
            ->assertJsonMissingPath('financial');

        $user->givePermissionTo('view_global_stats');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/overview')
            ->assertOk()
            ->assertJsonStructure([
                'financial' => [
                    'total_projects',
                ],
            ]);
    }
}
