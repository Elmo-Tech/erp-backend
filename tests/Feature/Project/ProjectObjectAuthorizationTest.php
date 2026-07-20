<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectHistory;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectObjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_capability_cannot_delete_a_foreign_project(): void
    {
        $outsider = $this->userWithPermissions(['delete_projects']);
        $project = Project::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    public function test_member_and_global_manager_preserve_legitimate_project_deletion(): void
    {
        $member = $this->userWithPermissions(['delete_projects']);
        $memberProject = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $memberProject->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MANAGER->value,
        ]);

        $this->actingAs($member, 'sanctum')
            ->deleteJson("/api/v1/projects/{$memberProject->id}")
            ->assertOk();

        $globalManager = $this->userWithPermissions(['manage_all_projects']);
        $globalProject = Project::factory()->create();

        $this->actingAs($globalManager, 'sanctum')
            ->deleteJson("/api/v1/projects/{$globalProject->id}")
            ->assertOk();

        $this->assertSoftDeleted('projects', ['id' => $memberProject->id]);
        $this->assertSoftDeleted('projects', ['id' => $globalProject->id]);
    }

    public function test_project_history_requires_parent_project_visibility(): void
    {
        $outsider = $this->userWithPermissions(['view_projects', 'view_project_history']);
        $member = $this->userWithPermissions(['view_projects', 'view_project_history']);
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        ProjectHistory::create([
            'project_id' => $project->id,
            'field_name' => 'budget',
            'old_value' => '1000',
            'new_value' => '2500',
            'reason' => 'Sensitive audit marker',
            'changed_by' => $member->id,
        ]);

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/history")
            ->assertForbidden();

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/history?filter[fieldName]=budget")
            ->assertOk()
            ->assertJsonPath('data.histories.0.reason', 'Sensitive audit marker');
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
