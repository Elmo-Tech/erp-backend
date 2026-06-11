<?php

namespace Tests\Feature\Notes;

use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_list_append_only_client_notes(): void
    {
        $user = $this->userWithPermissions(['view_internal_notes']);
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notes', [
                'modelType' => 'client',
                'modelId' => $client->id,
                'body' => 'Asked for a revised payment plan.',
                'notedAt' => '2026-05-10T12:30:00+00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Asked for a revised payment plan.')
            ->assertJsonPath('data.author.userId', $user->id);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/notes?modelType=client&modelId={$client->id}")
            ->assertOk()
            ->assertJsonPath('data.notes.0.body', 'Asked for a revised payment plan.')
            ->assertJsonPath('data.notes.0.author.name', $user->name);
    }

    public function test_user_can_create_and_list_project_notes(): void
    {
        $user = $this->userWithPermissions(['view_internal_notes']);
        $project = Project::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notes', [
                'modelType' => 'project',
                'modelId' => $project->id,
                'body' => 'Launch date confirmed with PM.',
            ])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/notes?modelType=project&modelId={$project->id}")
            ->assertOk()
            ->assertJsonPath('data.notes.0.body', 'Launch date confirmed with PM.');
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
