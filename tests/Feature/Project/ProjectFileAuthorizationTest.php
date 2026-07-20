<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectFile;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Resources\ProjectResource;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectFileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_portal_only_receives_client_visible_project_files(): void
    {
        $clientUser = $this->userWithPermissions(['view_projects', 'access_client_portal']);
        $client = $this->ensureClientProfile($clientUser);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $visible = $this->projectFile($project, ['name' => 'Client delivery', 'is_client_visible' => true]);
        $hidden = $this->projectFile($project, ['name' => 'Internal notes', 'is_client_visible' => false]);

        $this->actingAs($clientUser, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/files")
            ->assertOk()
            ->assertJsonPath('data.files.0.fileId', $visible->id)
            ->assertJsonCount(1, 'data.files');

        $this->actingAs($clientUser, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/files/{$hidden->id}")
            ->assertNotFound();

        $projectPayload = (new ProjectResource($project->load('files')))->resolve(request());
        $this->assertCount(1, $projectPayload['files']);
        $this->assertSame($visible->id, $projectPayload['files'][0]['fileId']);
    }

    public function test_project_member_only_receives_files_for_their_project_role(): void
    {
        $project = Project::factory()->create();
        $developer = $this->projectMember($project, ProjectRole::DEVELOPER, ['view_projects', 'view_project_files']);
        $manager = $this->projectMember($project, ProjectRole::MANAGER, ['view_projects', 'view_project_files']);
        $unrestricted = $this->projectFile($project, ['name' => 'Everyone']);
        $managerOnly = $this->projectFile($project, [
            'name' => 'Manager plan',
            'visible_to_roles' => [ProjectRole::MANAGER->value],
        ]);

        $this->actingAs($developer, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/files")
            ->assertOk()
            ->assertJsonPath('data.files.0.fileId', $unrestricted->id)
            ->assertJsonCount(1, 'data.files');

        $this->actingAs($developer, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/files/{$managerOnly->id}")
            ->assertNotFound();

        $this->actingAs($developer, 'sanctum')
            ->get("/api/v1/projects/{$project->id}/files/{$managerOnly->id}/download")
            ->assertNotFound();

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/files/{$managerOnly->id}")
            ->assertOk();
    }

    public function test_uploaded_project_file_is_private_and_delivered_through_authorized_route(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $project = Project::factory()->create();
        $uploader = $this->projectMember($project, ProjectRole::DEVELOPER, [
            'view_projects',
            'view_project_files',
            'upload_project_files',
        ]);

        $response = $this->actingAs($uploader, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/files", [
                'name' => 'Private specification',
                'type' => 'file',
                'file' => UploadedFile::fake()->createWithContent('specification.txt', 'private-content'),
            ])
            ->assertCreated();

        $file = ProjectFile::query()->sole();
        Storage::disk('local')->assertExists($file->file_path);
        Storage::disk('public')->assertMissing($file->file_path);
        $response->assertJsonPath(
            'data.fileUrl',
            route('projects.files.download', ['project' => $project, 'file' => $file])
        );

        $this->actingAs($uploader, 'sanctum')
            ->get("/api/v1/projects/{$project->id}/files/{$file->id}/download")
            ->assertOk()
            ->assertStreamedContent('private-content');
    }

    public function test_non_downloadable_file_has_no_delivery_url_and_cannot_be_downloaded(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create();
        $member = $this->projectMember($project, ProjectRole::DEVELOPER, ['view_projects', 'view_project_files']);
        $file = $this->projectFile($project, [
            'file_path' => "projects/{$project->id}/files/internal.txt",
            'is_downloadable' => false,
        ]);
        Storage::disk('local')->put($file->file_path, 'internal');

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/files/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.fileUrl', null);

        $this->actingAs($member, 'sanctum')
            ->get("/api/v1/projects/{$project->id}/files/{$file->id}/download")
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function projectFile(Project $project, array $attributes = []): ProjectFile
    {
        return ProjectFile::create(array_merge([
            'project_id' => $project->id,
            'name' => 'Project file',
            'type' => 'file',
            'file_path' => "projects/{$project->id}/files/file.txt",
            'is_client_visible' => false,
            'is_downloadable' => true,
            'visible_to_roles' => null,
        ], $attributes));
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

    /**
     * @param  array<int, string>  $permissions
     */
    private function projectMember(Project $project, ProjectRole $role, array $permissions): User
    {
        $user = $this->userWithPermissions($permissions);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role->value,
        ]);

        return $user;
    }
}
