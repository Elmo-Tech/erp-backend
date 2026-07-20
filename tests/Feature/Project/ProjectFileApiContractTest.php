<?php

namespace Tests\Feature\Project;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectFile;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectFileApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_file_index_returns_lightweight_list_contract(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'manage_all_projects', 'view_project_files']);
        $project = Project::factory()->create();

        ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'Launch Brief',
            'type' => 'link',
            'url' => 'https://example.test/launch-brief',
            'mime_type' => null,
            'size' => null,
            'description' => 'Kickoff document',
            'category' => 'document',
            'is_client_visible' => true,
            'is_downloadable' => true,
            'visible_to_roles' => ['manager'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/files");

        $response->assertOk()
            ->assertJsonPath('data.files.0.name', 'Launch Brief')
            ->assertJsonPath('data.files.0.fileUrl', 'https://example.test/launch-brief')
            ->assertJsonMissingPath('data.files.0.isClientVisible')
            ->assertJsonMissingPath('data.files.0.isDownloadable')
            ->assertJsonMissingPath('data.files.0.visibleToRoles')
            ->assertJsonMissingPath('data.files.0.createdBy');

        $this->assertSame([
            'fileId',
            'projectId',
            'name',
            'type',
            'fileUrl',
            'mimeType',
            'size',
            'description',
            'category',
            'createdAt',
        ], array_keys($response->json('data.files.0')));
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
