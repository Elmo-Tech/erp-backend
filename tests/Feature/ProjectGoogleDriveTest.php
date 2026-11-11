<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectGoogleDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_drive.client_id' => 'client-id',
            'services.google_drive.client_secret' => 'client-secret',
            'services.google_drive.redirect_uri' => 'http://localhost:3000/dashboard/integrations/google-drive/callback',
        ]);

        Permission::firstOrCreate(['name' => 'upload_project_files']);
        Permission::firstOrCreate(['name' => 'manage_project_files']);
        Permission::firstOrCreate(['name' => 'view_all_projects']);
        Role::firstOrCreate(['name' => 'client']);
    }

    public function test_drive_oauth_url_requires_authentication(): void
    {
        $this->getJson('/api/v1/google-drive/oauth-url')
            ->assertUnauthorized();
    }

    public function test_drive_status_requires_authentication(): void
    {
        $this->getJson('/api/v1/google-drive/status')
            ->assertUnauthorized();
    }

    public function test_user_without_drive_management_access_cannot_see_connection_status(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/google-drive/status')
            ->assertForbidden();
    }

    public function test_authenticated_user_can_see_disconnected_drive_status(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $user->givePermissionTo('manage_project_files');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/google-drive/status')
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.reason', 'Google Drive is not connected.');

        Http::assertNothingSent();
    }

    public function test_authenticated_user_can_see_connected_drive_status(): void
    {
        $expiresAt = now()->addHour()->startOfSecond();
        $user = User::factory()->create();
        $user->givePermissionTo('manage_project_files');
        $this->storeDriveConnection($expiresAt);

        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => [
                    'emailAddress' => 'drive-account@example.com',
                ],
            ]),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/google-drive/status');

        $response->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.accountEmail', 'drive-account@example.com')
            ->assertJsonPath('data.expiresAt', $expiresAt->toIso8601String());

        $this->assertArrayNotHasKey('accessToken', $response->json('data'));
        $this->assertArrayNotHasKey('refreshToken', $response->json('data'));
        $this->assertArrayNotHasKey('access_token', $response->json('data'));
        $this->assertArrayNotHasKey('refresh_token', $response->json('data'));
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/about'));
    }

    public function test_authorized_user_can_connect_project_to_drive_folder(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage_project_files');
        $project = Project::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/drive-folder", [
                'folderId' => 'folder-123',
                'folderName' => 'Client Docs',
                'folderUrl' => 'https://drive.google.com/drive/folders/folder-123',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.googleDriveFolderId', 'folder-123')
            ->assertJsonPath('data.googleDriveFolderName', 'Client Docs');

        $project->refresh();
        $this->assertSame('folder-123', $project->google_drive_folder_id);
    }

    public function test_user_without_project_file_access_cannot_list_drive_files(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_all_projects');
        $project = Project::factory()->create([
            'google_drive_folder_id' => 'folder-123',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/drive-files")
            ->assertForbidden();
    }

    public function test_project_member_can_list_drive_files(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'google_drive_folder_id' => 'folder-123',
        ]);
        $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $this->storeDriveConnection();

        Http::fake([
            'https://www.googleapis.com/drive/v3/files*' => Http::response([
                'files' => [[
                    'id' => 'file-1',
                    'name' => 'Brief.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => '1200',
                    'webViewLink' => 'https://drive.google.com/file/d/file-1/view',
                    'webContentLink' => 'https://drive.google.com/uc?id=file-1',
                    'createdTime' => '2026-06-01T12:00:00.000Z',
                    'modifiedTime' => '2026-06-02T12:00:00.000Z',
                ]],
            ]),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/drive-files");

        $response->assertOk()
            ->assertJsonPath('data.files.0.id', 'file-1')
            ->assertJsonPath('data.files.0.name', 'Brief.pdf')
            ->assertJsonPath('data.files.0.size', 1200);
    }

    public function test_project_member_can_upload_file_to_connected_drive_folder(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'google_drive_folder_id' => 'folder-123',
        ]);
        $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $this->storeDriveConnection();

        Http::fake([
            'https://www.googleapis.com/upload/drive/v3/files*' => Http::sequence()->push('', 200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=test'])->push([
                'id' => 'uploaded-1',
                'name' => 'logo.png',
                'mimeType' => 'image/png',
                'size' => '9',
                'webViewLink' => 'https://drive.google.com/file/d/uploaded-1/view',
                'webContentLink' => 'https://drive.google.com/uc?id=uploaded-1',
                'createdTime' => '2026-06-01T12:00:00.000Z',
                'modifiedTime' => '2026-06-01T12:00:00.000Z',
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/v1/projects/{$project->id}/drive-files", [
                'file' => UploadedFile::fake()->image('logo.png'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.file.id', 'uploaded-1')
            ->assertJsonPath('data.file.name', 'logo.png');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'upload/drive/v3/files'));
    }

    public function test_expired_drive_connection_is_refreshed_during_status_check(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage_project_files');
        $this->storeDriveConnection(expiresAt: now()->subMinute());
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
            'https://www.googleapis.com/drive/v3/about*' => Http::response(['user' => ['emailAddress' => 'test@example.com']]),
        ]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/google-drive/status')->assertOk()->assertJsonPath('data.connected', true);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/about') && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }

    public function test_upload_rejects_untrusted_session_url(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['google_drive_folder_id' => 'folder-123']);
        $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $this->storeDriveConnection();
        Http::fake(['https://www.googleapis.com/upload/drive/v3/files*' => Http::response('', 200, ['Location' => 'https://example.com/upload/drive/v3/files'])]);
        $this->actingAs($user, 'sanctum')->post("/api/v1/projects/{$project->id}/drive-files", ['file' => UploadedFile::fake()->create('video.mp4', 10, 'video/mp4')])->assertStatus(502);
        Http::assertSentCount(1);
    }

    public function test_expired_drive_token_is_refreshed_before_listing_files(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'google_drive_folder_id' => 'folder-123',
        ]);
        $project->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $this->storeDriveConnection(expiresAt: now()->subMinute());

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fresh-token',
                'expires_in' => 3600,
            ]),
            'https://www.googleapis.com/drive/v3/files*' => Http::response(['files' => []]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/drive-files")
            ->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token');
    }

    public function test_client_role_cannot_use_internal_drive_files_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $client = $this->ensureClientProfile($user);
        $project = Project::factory()->create([
            'client_id' => $client->id,
            'google_drive_folder_id' => 'folder-123',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/drive-files")
            ->assertForbidden();
    }

    private function storeDriveConnection(?Carbon $expiresAt = null): void
    {
        DB::table('google_drive_connections')->insert([
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'expires_at' => $expiresAt ?? now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
