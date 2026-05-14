<?php

namespace Tests\Feature\Meetings;

use App\Modules\Client\Models\Client;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MeetingSubjectValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_meeting_can_be_created_with_client_morph_alias_subject_type(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('schedule_meetings', 'web');
        $user->givePermissionTo('schedule_meetings');

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
}
