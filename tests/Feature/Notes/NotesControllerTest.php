<?php

namespace Tests\Feature\Notes;

use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Enums\ProjectRole;
use App\Modules\Client\Models\Client;
use App\Modules\Lead\Models\Lead;
use App\Modules\Notes\Models\Note;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_list_append_only_client_notes(): void
    {
        $user = $this->userWithPermissions(['view_internal_notes', 'create_internal_notes', 'view_clients']);
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
        $user = $this->userWithPermissions(['view_internal_notes', 'create_internal_notes', 'view_projects']);
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

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

    public function test_read_only_user_cannot_create_an_authorized_client_note(): void
    {
        $user = $this->userWithPermissions(['view_internal_notes', 'view_clients']);
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notes', $this->notePayload('client', $client->id))
            ->assertForbidden();

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_user_cannot_write_a_note_to_an_unauthorized_client(): void
    {
        $user = $this->userWithPermissions(['view_internal_notes', 'create_internal_notes']);
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notes', $this->notePayload('client', $client->id))
            ->assertForbidden();

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_user_cannot_read_notes_from_an_unauthorized_project(): void
    {
        $user = $this->userWithPermissions(['view_internal_notes', 'view_projects']);
        $project = Project::factory()->create();
        $this->noteFor($project, User::factory()->create());

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/notes?modelType=project&modelId={$project->id}")
            ->assertForbidden();
    }

    public function test_lead_notes_require_lead_visibility_for_read_and_write(): void
    {
        $user = $this->userWithPermissions(['view_internal_notes', 'create_internal_notes']);
        $lead = Lead::create([
            'title' => 'Private lead',
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
        ]);
        $this->noteFor($lead, User::factory()->create());

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/notes?modelType=lead&modelId={$lead->id}")
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notes', $this->notePayload('lead', $lead->id))
            ->assertForbidden();

        $this->assertDatabaseCount('notes', 1);
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
     * @return array<string, mixed>
     */
    private function notePayload(string $modelType, int $modelId): array
    {
        return [
            'modelType' => $modelType,
            'modelId' => $modelId,
            'body' => 'Unauthorized note body.',
        ];
    }

    private function noteFor(Client|Project|Lead $subject, User $author): Note
    {
        return Note::create([
            'model_type' => get_class($subject),
            'model_id' => $subject->id,
            'body' => 'Existing private note.',
            'noted_at' => now(),
            'created_by' => $author->id,
        ]);
    }
}
