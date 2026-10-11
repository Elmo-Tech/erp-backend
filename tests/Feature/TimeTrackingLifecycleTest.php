<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TimerStopReason;
use App\Events\TimerUpdated;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Sales\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TimeTrackingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_timer_remains_active_through_two_heartbeat_intervals(): void
    {
        Event::fake([TimerUpdated::class]);
        $user = User::factory()->create();
        $task = Task::factory()->create();
        $entry = app(TimeTrackingService::class)->startTimer($user, $task);

        for ($interval = 0; $interval < 2; $interval++) {
            $this->travel(45)->seconds();
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/time-entries/heartbeat', [
                    'timeEntryId' => $entry->id,
                    'sessionId' => $entry->session_id,
                ])
                ->assertOk()
                ->assertJsonPath('data.isActive', true);
            $this->getJson('/api/v1/time-entries/active')
                ->assertOk()
                ->assertJsonPath('data.timeEntryId', $entry->id)
                ->assertJsonPath('data.isActive', true);
        }

        $this->assertNull($entry->fresh()->end_time);
        $this->travelBack();
    }

    public function test_stale_timer_stops_at_last_heartbeat(): void
    {
        Event::fake([TimerUpdated::class]);
        $user = User::factory()->create();
        $task = Task::factory()->create();
        $heartbeatAt = Carbon::now('Africa/Cairo')->subMinutes(5)->startOfSecond();
        $entry = TimeEntry::factory()->create([
            'user_id' => $user->id,
            'trackable_id' => $task->id,
            'trackable_type' => Task::class,
            'start_time' => $heartbeatAt->copy()->subMinutes(10),
            'end_time' => null,
            'duration_minutes' => null,
            'session_id' => fake()->uuid(),
            'last_heartbeat_at' => $heartbeatAt,
        ]);

        $active = app(TimeTrackingService::class)->getActiveTimer($user);

        $this->assertNull($active);
        $entry->refresh();
        $this->assertSame(TimerStopReason::CONNECTION_LOST, $entry->stop_reason);
        $this->assertTrue($entry->end_time->equalTo($heartbeatAt));
    }

    public function test_pause_hold_resume_and_end_have_distinct_lifecycle_reasons(): void
    {
        Event::fake([TimerUpdated::class]);
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $service = app(TimeTrackingService::class);

        $started = $service->startTimer($user, $task);
        $paused = $service->pauseTimer($user);
        $this->assertSame(TimerStopReason::PAUSE, $paused->stop_reason);

        $resumed = $service->resumeTimer($user, $paused);
        $this->assertSame($paused->id, $resumed->resumed_from_id);

        $held = $service->holdTimer($user, 'Waiting for client assets');
        $this->assertSame(TimerStopReason::HOLD, $held->stop_reason);
        $this->assertSame(TaskStatus::ON_HOLD, $task->fresh()->status);
        $this->assertSame('Waiting for client assets', $task->fresh()->hold_reason);

        $resumedFromHold = $service->resumeTimer($user, $held);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
        $this->assertNull($task->fresh()->hold_reason);

        $ended = $service->stopTimer($user, TimerStopReason::END);
        $this->assertSame(TimerStopReason::END, $ended->stop_reason);
        $this->assertSame($resumedFromHold->id, $ended->id);
        $this->assertNotSame($started->id, $resumed->id);
    }

    public function test_pipeline_hold_and_resume_move_task_between_workflow_stages(): void
    {
        Event::fake([TimerUpdated::class]);
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $backlogStage = $project->workflowStages()->where('category', 'backlog')->firstOrFail();
        $activeStage = $project->workflowStages()->where('category', 'active')->firstOrFail();
        $holdStage = $project->workflowStages()->where('category', 'paused')->firstOrFail();
        $task = Task::factory()->for($project)->create([
            'workflow_stage_id' => $backlogStage->id,
            'is_pipeline_task' => true,
        ]);
        $service = app(TimeTrackingService::class);

        $service->startTimer($user, $task);
        $this->assertSame($activeStage->id, $task->fresh()->workflow_stage_id);

        $held = $service->holdTimer($user, 'Waiting for assets');
        $this->assertSame($holdStage->id, $task->fresh()->workflow_stage_id);

        $service->resumeTimer($user, $held);
        $this->assertSame($activeStage->id, $task->fresh()->workflow_stage_id);
    }
}
