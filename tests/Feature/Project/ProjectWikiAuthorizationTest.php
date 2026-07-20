<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectWiki;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectWikiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_visibility_does_not_grant_wiki_visibility(): void
    {
        $clientUser = $this->userWithPermissions(['view_projects', 'access_client_portal']);
        $client = $this->ensureClientProfile($clientUser);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $wiki = $this->encryptedWiki($project, 'client-visible-secret');

        $this->actingAs($clientUser, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/wikis/{$wiki->id}")
            ->assertForbidden();
    }

    public function test_wiki_reader_cannot_receive_decrypted_content_without_sensitive_content_permission(): void
    {
        [$project, $reader] = $this->projectMemberWithPermissions([
            'view_projects',
            'view_wiki',
        ]);
        $encryptedWiki = $this->encryptedWiki($project, 'database-password');
        $plainWiki = ProjectWiki::create([
            'project_id' => $project->id,
            'title' => 'Public runbook',
            'content' => 'Safe operational instructions',
            'is_encrypted' => false,
        ]);

        $this->actingAs($reader, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/wikis")
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Public runbook',
                'content' => 'Safe operational instructions',
            ])
            ->assertJsonFragment([
                'title' => 'Credentials',
                'content' => '',
            ]);

        $this->actingAs($reader, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/wikis/{$encryptedWiki->id}")
            ->assertOk()
            ->assertJsonPath('data.content', '')
            ->assertJsonMissing(['content' => 'database-password']);
    }

    public function test_sensitive_wiki_reader_can_receive_decrypted_content(): void
    {
        [$project, $reader] = $this->projectMemberWithPermissions([
            'view_projects',
            'view_wiki',
            'view_encrypted_content',
        ]);
        $wiki = $this->encryptedWiki($project, 'authorized-secret');

        $this->actingAs($reader, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/wikis/{$wiki->id}")
            ->assertOk()
            ->assertJsonPath('data.content', 'authorized-secret');
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
     * @return array{Project, User}
     */
    private function projectMemberWithPermissions(array $permissions): array
    {
        $project = Project::factory()->create();
        $member = $this->userWithPermissions($permissions);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        return [$project, $member];
    }

    private function encryptedWiki(Project $project, string $content): ProjectWiki
    {
        return ProjectWiki::create([
            'project_id' => $project->id,
            'title' => 'Credentials',
            'content' => Crypt::encryptString($content),
            'is_encrypted' => true,
        ]);
    }
}
