<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardRecentActivitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_recent_activities_use_task_activity_actor_and_description(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'developer']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $actor = User::factory()->create(['name' => 'QA Actor']);
        $task = Task::factory()->create(['title' => 'Fix release gate']);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'type' => TaskActivity::TYPE_COMMENT,
            'description' => 'Added comment',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.recentActivities.0.user', 'QA Actor')
            ->assertJsonPath('data.recentActivities.0.action', 'Added comment: Fix release gate')
            ->assertJsonPath('data.recentActivities.0.type', 'task');
    }
}
