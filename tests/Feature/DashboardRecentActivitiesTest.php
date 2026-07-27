<?php

namespace Tests\Feature;

use App\Models\Project;
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

    public function test_admin_dashboard_includes_project_and_task_delivery_timeline(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'developer']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $project = Project::factory()->create([
            'title' => 'ERP rollout',
            'created_at' => '2026-07-01',
            'deadline' => '2026-08-15',
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Build reporting dashboard',
            'start_date' => '2026-07-10',
            'due_date' => '2026-07-20',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/dashboard/overview')
            ->assertOk()
            ->assertJsonFragment([
                'id' => "project-{$project->id}",
                'entityType' => 'project',
                'title' => 'ERP rollout',
                'startDate' => '2026-07-01',
                'endDate' => '2026-08-15',
                'projectId' => $project->id,
                'projectName' => 'ERP rollout',
            ])
            ->assertJsonFragment([
                'id' => "task-{$task->id}",
                'entityType' => 'task',
                'title' => 'Build reporting dashboard',
                'startDate' => '2026-07-10',
                'endDate' => '2026-07-20',
                'projectId' => $project->id,
                'projectName' => 'ERP rollout',
            ]);
    }
}
