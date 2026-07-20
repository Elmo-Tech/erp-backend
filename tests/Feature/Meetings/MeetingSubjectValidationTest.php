<?php

namespace Tests\Feature\Meetings;

use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Enums\ProjectRole;
use App\Modules\Client\Models\Client;
use App\Modules\Lead\Models\Lead;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MeetingSubjectValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_meeting_can_be_created_with_client_morph_alias_subject_type(): void
    {
        $user = $this->userWithPermissions(['schedule_meetings', 'view_clients']);

        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/meetings', [
                'subjectType' => 'client',
                'subjectId' => $client->id,
                'title' => 'Discovery call',
                'scheduledAt' => now()->addDay()->toISOString(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('meetings', [
            'model_type' => Client::class,
            'model_id' => $client->id,
            'title' => 'Discovery call',
        ]);

        $this->assertSame(1, Meeting::count());
    }

    public function test_meeting_cannot_be_created_for_an_unauthorized_client(): void
    {
        $user = $this->userWithPermissions(['schedule_meetings']);
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/meetings', $this->meetingPayload(Client::class, $client->id))
            ->assertForbidden();

        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_project_subject_requires_membership_and_preserves_member_creation(): void
    {
        $user = $this->userWithPermissions(['schedule_meetings', 'view_projects']);
        $project = Project::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/meetings', $this->meetingPayload(Project::class, $project->id))
            ->assertForbidden();

        $this->assertDatabaseCount('meetings', 0);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/meetings', $this->meetingPayload(Project::class, $project->id))
            ->assertCreated();

        $this->assertDatabaseHas('meetings', [
            'model_type' => Project::class,
            'model_id' => $project->id,
        ]);
    }

    public function test_lead_subject_requires_lead_access_and_preserves_authorized_creation(): void
    {
        $user = $this->userWithPermissions(['schedule_meetings']);
        $lead = Lead::create([
            'title' => 'Scoped lead',
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/meetings', $this->meetingPayload(Lead::class, $lead->id))
            ->assertForbidden();

        $this->assertDatabaseCount('meetings', 0);

        $user->givePermissionTo(Permission::findOrCreate('view_leads', 'web'));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/meetings', $this->meetingPayload(Lead::class, $lead->id))
            ->assertCreated();
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
    private function meetingPayload(string $subjectType, int $subjectId): array
    {
        return [
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
            'title' => 'Scoped subject meeting',
            'scheduledAt' => now()->addDay()->toISOString(),
        ];
    }
}
