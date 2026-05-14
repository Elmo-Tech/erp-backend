<?php

namespace Tests\Feature\Meetings;

use App\Modules\Client\Models\Client;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MeetingSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendee_can_see_meeting_in_index_and_today_schedule(): void
    {
        Carbon::setTestNow('2026-05-14 09:00:00');

        $creator = $this->userWithPermissions(['schedule_meetings']);
        $attendee = $this->userWithPermissions(['view_meetings']);
        $client = Client::factory()->create();

        Meeting::create([
            'model_id' => $client->id,
            'model_type' => Client::class,
            'status' => 'scheduled',
            'title' => 'Discovery Call',
            'meeting_type' => 'discovery-call',
            'internal_attendees' => [$attendee->id],
            'external_attendees' => ['primary-contact'],
            'assigned_to' => $creator->id,
            'created_by' => $creator->id,
            'scheduled_at' => '2026-05-14 10:00:00',
        ]);

        $this->actingAs($attendee, 'sanctum')
            ->getJson('/api/v1/meetings')
            ->assertOk()
            ->assertJsonPath('data.meetings.0.title', 'Discovery Call');

        $this->actingAs($attendee, 'sanctum')
            ->getJson('/api/v1/schedule/today')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Discovery Call');
    }

    public function test_store_persists_attendees_and_notifies_internal_attendees(): void
    {
        Carbon::setTestNow('2026-05-14 09:00:00');

        $creator = $this->userWithPermissions(['schedule_meetings']);
        $attendee = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/meetings', [
                'subjectType' => Client::class,
                'subjectId' => $client->id,
                'meetingType' => 'technical-review',
                'title' => 'Technical Review',
                'scheduledAt' => '2026-05-14T11:00:00+00:00',
                'location' => 'online',
                'locationDetails' => 'https://meet.example.com/review',
                'internalAttendees' => [$attendee->id],
                'externalAttendees' => ['technical-contact'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.internalAttendees.0', $attendee->id)
            ->assertJsonPath('data.meetingType', 'technical-review');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $attendee->id,
        ]);
    }

    public function test_meeting_status_can_be_updated_through_meeting_update_route(): void
    {
        Carbon::setTestNow('2026-05-14 09:00:00');

        $user = $this->userWithPermissions(['schedule_meetings']);
        $client = Client::factory()->create();

        $meeting = Meeting::create([
            'model_id' => $client->id,
            'model_type' => Client::class,
            'status' => 'scheduled',
            'title' => 'Discovery Call',
            'meeting_type' => 'discovery-call',
            'internal_attendees' => [$user->id],
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'scheduled_at' => '2026-05-14 10:00:00',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/meetings/{$meeting->id}", [
                'status' => 'completed',
                'outcomeSummary' => 'Decision made.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.outcomeSummary', 'Decision made.');

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => 'completed',
            'is_completed' => true,
            'outcome_summary' => 'Decision made.',
        ]);
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
