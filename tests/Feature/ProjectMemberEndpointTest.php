<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectMemberEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_member_endpoint_returns_existing_members(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $member->id, 'role' => 'member']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/members")
            ->assertOk()
            ->assertJsonPath('data.members.0.userId', $member->id)
            ->assertJsonPath('data.members.0.user.name', $member->name);
    }
}
