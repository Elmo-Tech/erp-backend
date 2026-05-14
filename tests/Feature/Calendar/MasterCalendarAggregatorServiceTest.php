<?php

namespace Tests\Feature\Calendar;

use App\Enums\MilestoneStatus;
use App\Modules\Calendar\Services\MasterCalendarAggregatorService;
use App\Modules\Client\Models\Client;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MasterCalendarAggregatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_project_milestones_in_calendar_feed(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create(['title' => 'Client Portal Relaunch']);

        $milestone = Milestone::create([
            'project_id' => $project->id,
            'title' => 'Design Sign-off',
            'status' => MilestoneStatus::ACTIVE,
            'progress_percentage' => 40,
            'due_date' => '2026-05-20',
        ]);

        $items = app(MasterCalendarAggregatorService::class)
            ->aggregate(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'));

        $this->assertContains('milestone-'.$milestone->id, $items->pluck('id')->all());

        $item = $items->firstWhere('id', 'milestone-'.$milestone->id);

        $this->assertSame('milestone', $item['type']);
        $this->assertSame('Design Sign-off', $item['title']);
        $this->assertSame('2026-05-20', $item['date']);
        $this->assertSame('active', $item['status']);
        $this->assertSame($project->id, $item['meta']['projectId']);
        $this->assertSame('Client Portal Relaunch', $item['meta']['projectTitle']);
        $this->assertSame(40, $item['meta']['progressPercentage']);
    }

    public function test_it_includes_scheduled_meetings_in_calendar_feed(): void
    {
        $creator = User::factory()->create(['name' => 'Mona Scheduler']);
        $attendee = User::factory()->create();
        $client = Client::factory()->create(['display_name' => 'Acme']);

        $meeting = Meeting::create([
            'model_id' => $client->id,
            'model_type' => Client::class,
            'status' => 'scheduled',
            'title' => 'Discovery Call',
            'meeting_type' => 'discovery-call',
            'internal_attendees' => [$attendee->id],
            'external_attendees' => ['primary-contact'],
            'assigned_to' => $attendee->id,
            'created_by' => $creator->id,
            'scheduled_at' => '2026-05-20 14:30:00',
        ]);

        $items = app(MasterCalendarAggregatorService::class)
            ->aggregate(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'));

        $item = $items->firstWhere('id', 'meeting-'.$meeting->id);

        $this->assertNotNull($item);
        $this->assertSame('meeting', $item['type']);
        $this->assertSame('2026-05-20', $item['date']);
        $this->assertStringStartsWith('2026-05-20T14:30:00', $item['startTime']);
        $this->assertSame('Mona Scheduler', $item['meta']['creatorName']);
    }
}
