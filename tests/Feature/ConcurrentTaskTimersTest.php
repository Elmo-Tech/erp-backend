<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Services\Sales\TimeTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrentTaskTimersTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_different_task_timers_can_run_and_be_paused_independently(): void
    {
        $user = User::factory()->create();
        $tasks = Task::factory()->count(3)->create();
        $service = app(TimeTrackingService::class);
        $first = $service->startTimer($user, $tasks[0]);
        $second = $service->startTimer($user, $tasks[1]);
        $this->assertCount(2, $service->getActiveTimersForUser($user));
        try { $service->pauseTimer($user); $this->fail('Ambiguous stop must fail'); } catch (\RuntimeException $exception) { $this->assertStringContainsString('Select', $exception->getMessage()); }
        $service->pauseTimer($user, $first->id);
        $this->assertNotNull($first->fresh()->end_time);
        $this->assertNull($second->fresh()->end_time);
        $third = $service->startTimer($user, $tasks[2]);
        $this->assertCount(2, $service->getActiveTimersForUser($user));
        $other = User::factory()->create();
        try { $service->pauseTimer($other, $third->id); $this->fail('Must not control another user timer'); } catch (\RuntimeException $exception) { $this->assertNull($third->fresh()->end_time); }
    }

    public function test_api_lists_both_timers_and_only_pauses_the_selected_timer(): void
    {
        $user = User::factory()->create();
        $tasks = Task::factory()->count(2)->create();
        $service = app(TimeTrackingService::class);
        $first = $service->startTimer($user, $tasks[0]);
        $second = $service->startTimer($user, $tasks[1]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/time-entries/my-active')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/v1/time-entries/pause', ['timeEntryId' => $second->id])->assertOk()->assertJsonPath('data.timeEntryId', $second->id);
        $this->assertNull($first->fresh()->end_time);
        $this->assertNotNull($second->fresh()->end_time);
    }

    public function test_limit_and_duplicate_task_are_enforced(): void
    {
        $user = User::factory()->create();
        $tasks = Task::factory()->count(3)->create();
        $service = app(TimeTrackingService::class);
        $service->startTimer($user, $tasks[0]);
        try { $service->startTimer($user, $tasks[0]); $this->fail('duplicate'); } catch (\RuntimeException $exception) { $this->assertStringContainsString('already', $exception->getMessage()); }
        $service->startTimer($user, $tasks[1]);
        $this->expectExceptionMessage('up to two');
        $service->startTimer($user, $tasks[2]);
    }
}
