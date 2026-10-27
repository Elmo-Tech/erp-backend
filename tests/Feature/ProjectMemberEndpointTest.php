<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectMemberEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_project_member_cannot_add_change_or_remove_members(): void
    {
        $employee = User::factory()->create();
        $employee->givePermissionTo(Permission::findOrCreate('view_projects', 'web'));
        $project = Project::factory()->create();
        $membership = $project->members()->create(['user_id' => $employee->id, 'role' => 'member']);
        $other = User::factory()->create();

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/members", ['userId' => $other->id])
            ->assertForbidden();
        $this->putJson("/api/v1/projects/{$project->id}/members/{$membership->id}", ['role' => 'lead'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$membership->id}")
            ->assertForbidden();
        $this->assertDatabaseCount('project_members', 1);
        $this->assertDatabaseHas('project_members', ['id' => $membership->id, 'role' => 'member']);
    }

    public function test_project_member_mutations_respect_independent_action_permissions(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::findOrCreate('add_project_members', 'web'));
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $manager->id, 'role' => 'member']);
        $member = User::factory()->create();
        $membershipId = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/members", ['userId' => $member->id])
            ->assertCreated()->json('data.memberId');
        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$membershipId}")->assertForbidden();
        $manager->givePermissionTo(Permission::findOrCreate('manage_project_members', 'web'));
        $this->putJson("/api/v1/projects/{$project->id}/members/{$membershipId}", ['role' => 'reviewer'])->assertOk();
        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$membershipId}")->assertOk();
    }

    public function test_project_member_endpoint_returns_existing_members(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $admin->givePermissionTo(Permission::findOrCreate('view_all_projects', 'web'));
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $member->id, 'role' => 'member']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/members")
            ->assertOk()
            ->assertJsonPath('data.members.0.userId', $member->id)
            ->assertJsonPath('data.members.0.user.name', $member->name);
    }

    public function test_project_history_includes_current_members_files_and_drive_folder(): void
    {
        Permission::firstOrCreate(['name' => 'view_project_history']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $admin->givePermissionTo([
            Permission::findOrCreate('view_all_projects', 'web'),
            'view_project_history',
        ]);
        $member = User::factory()->create(['name' => 'Team User']);
        $project = Project::factory()->create([
            'google_drive_folder_id' => 'folder-123',
            'google_drive_folder_name' => 'Client Docs',
        ]);
        $project->members()->create(['user_id' => $member->id, 'role' => 'member']);
        ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'Brief.pdf',
            'type' => 'link',
            'url' => 'https://example.com/brief.pdf',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/history")
            ->assertOk();

        $fields = collect($response->json('data.histories'))->pluck('fieldName')->all();
        $values = collect($response->json('data.histories'))->pluck('newValue')->all();

        $this->assertContains('team_member', $fields);
        $this->assertContains('project_file', $fields);
        $this->assertContains('google_drive_folder', $fields);
        $this->assertContains('Team User (member)', $values);
        $this->assertContains('Brief.pdf', $values);
        $this->assertContains('Client Docs', $values);
    }
}
