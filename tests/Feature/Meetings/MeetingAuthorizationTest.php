<?php

namespace Tests\Feature\Meetings;

use App\Enums\ProjectRole;
use App\Modules\Client\Models\Client;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MeetingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrelated_user_cannot_read_a_meeting(): void
    {
        $creator = $this->meetingUser();
        $outsider = $this->meetingUser();
        $meeting = $this->meetingFor($creator);

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}")
            ->assertForbidden();
    }

    public function test_unrelated_user_cannot_update_a_meeting(): void
    {
        $creator = $this->meetingUser();
        $outsider = $this->meetingUser();
        $meeting = $this->meetingFor($creator);

        $this->actingAs($outsider, 'sanctum')
            ->patchJson("/api/v1/meetings/{$meeting->id}", ['title' => 'Hijacked meeting'])
            ->assertForbidden();

        $this->assertSame('Scoped meeting', $meeting->fresh()->title);
    }

    public function test_unrelated_user_cannot_complete_a_meeting(): void
    {
        $creator = $this->meetingUser();
        $outsider = $this->meetingUser();
        $meeting = $this->meetingFor($creator);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/meetings/{$meeting->id}/complete", ['minutes' => 'Unauthorized minutes'])
            ->assertForbidden();

        $this->assertSame('scheduled', $meeting->fresh()->status->value);
    }

    public function test_unrelated_user_cannot_cancel_a_meeting(): void
    {
        $creator = $this->meetingUser();
        $outsider = $this->meetingUser();
        $meeting = $this->meetingFor($creator);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/meetings/{$meeting->id}/cancel", [
                'cancellationReason' => 'Unauthorized cancellation',
            ])
            ->assertForbidden();

        $this->assertSame('scheduled', $meeting->fresh()->status->value);
    }

    public function test_creator_and_internal_attendee_keep_legitimate_access(): void
    {
        $creator = $this->meetingUser();
        $attendee = $this->meetingUser();
        $meeting = $this->meetingFor($creator, $attendee);

        $this->actingAs($attendee, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}")
            ->assertOk();

        $this->actingAs($attendee, 'sanctum')
            ->patchJson("/api/v1/meetings/{$meeting->id}", ['title' => 'Attendee update'])
            ->assertOk();

        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/v1/meetings/{$meeting->id}/complete", ['minutes' => 'Approved minutes'])
            ->assertOk();
    }

    public function test_project_member_can_read_but_needs_project_update_authority_to_mutate(): void
    {
        $member = $this->meetingUser();
        $member->givePermissionTo(Permission::findOrCreate('view_projects', 'web'));
        $project = Project::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::DEVELOPER->value,
        ]);
        $meeting = Meeting::create([
            'model_id' => $project->id,
            'model_type' => Project::class,
            'status' => 'scheduled',
            'title' => 'Project meeting',
            'assigned_to' => User::factory()->create()->id,
            'created_by' => User::factory()->create()->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}")
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->patchJson("/api/v1/meetings/{$meeting->id}", ['title' => 'Unauthorized project update'])
            ->assertForbidden();

        $member->givePermissionTo(Permission::findOrCreate('edit_projects', 'web'));

        $this->actingAs($member, 'sanctum')
            ->patchJson("/api/v1/meetings/{$meeting->id}", ['title' => 'Authorized project update'])
            ->assertOk();
    }

    private function meetingUser(): User
    {
        $user = User::factory()->create();

        foreach (['view_meetings', 'schedule_meetings'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo(['view_meetings', 'schedule_meetings']);

        return $user;
    }

    private function meetingFor(User $creator, ?User $attendee = null): Meeting
    {
        $client = Client::factory()->create();

        return Meeting::create([
            'model_id' => $client->id,
            'model_type' => Client::class,
            'status' => 'scheduled',
            'title' => 'Scoped meeting',
            'internal_attendees' => $attendee ? [$attendee->id] : [],
            'assigned_to' => $creator->id,
            'created_by' => $creator->id,
            'scheduled_at' => now()->addDay(),
        ]);
    }
}
