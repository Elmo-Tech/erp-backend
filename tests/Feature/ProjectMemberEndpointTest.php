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

    public function test_project_history_includes_current_members_files_and_drive_folder(): void
    {
        Permission::firstOrCreate(['name' => 'view_project_history']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $admin->givePermissionTo('view_project_history');
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
