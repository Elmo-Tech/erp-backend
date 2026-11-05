<?php

namespace Tests\Feature;

use App\Enums\TimerStopReason;
use App\Events\NotificationCreated;
use App\Events\TimerUpdated;
use App\Jobs\SendWebPush;
use App\Models\Task;
use App\Models\HrPolicy;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Sales\TimeTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AfterHoursTimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_uses_each_employees_schedule_and_pauses_at_prompt_time(): void
    {
        Event::fake([NotificationCreated::class, TimerUpdated::class]);
        Bus::fake([SendWebPush::class]);
        $this->travelTo(now()->startOfDay()->setTime(17, 59));
        $user = User::factory()->create(['is_active' => true, 'work_start_time' => '10:00:00', 'work_hours_per_day' => 8]);
        $task = Task::factory()->create();
        $timer = TimeEntry::factory()->active()->create(['user_id' => $user->id, 'trackable_type' => Task::class, 'trackable_id' => $task->id, 'start_time' => now()->setTime(10, 0)]);
        $this->artisan('time-entries:check-after-hours')->assertSuccessful();
        $this->assertNull($timer->fresh()->after_hours_prompt_at);
        $this->travel(1)->minutes();
        $this->artisan('time-entries:check-after-hours')->assertSuccessful();
        $prompt = $timer->fresh()->after_hours_prompt_at;
        $this->assertNotNull($prompt);
        Bus::assertDispatchedTimes(SendWebPush::class, 1);
        $this->travel(10)->minutes();
        $this->artisan('time-entries:check-after-hours')->assertSuccessful();
        $timer->refresh();
        $this->assertSame(TimerStopReason::AFTER_HOURS_TIMEOUT, $timer->stopReason());
        $this->assertTrue($timer->end_time->equalTo($prompt));
        $this->assertSame($timer->id, app(TimeTrackingService::class)->getLatestResumableTimer($user)->id);
    }

    public function test_confirmation_repeats_after_an_hour_and_rejects_other_users_or_expired_prompts(): void
    {
        Event::fake([NotificationCreated::class, TimerUpdated::class]);
        Bus::fake([SendWebPush::class]);
        $this->travelTo(now()->startOfDay()->setTime(18, 0));
        $user = User::factory()->create(['is_active' => true, 'work_start_time' => '10:00:00', 'work_hours_per_day' => 8]);
        $other = User::factory()->create();
        $task = Task::factory()->create();
        $timer = TimeEntry::factory()->active()->create(['user_id' => $user->id, 'trackable_type' => Task::class, 'trackable_id' => $task->id, 'start_time' => now()->setTime(10, 0)]);
        $this->artisan('time-entries:check-after-hours')->assertSuccessful();
        $prompt = $timer->fresh()->after_hours_prompt_at->toIso8601String();
        $body = ['timeEntryId' => $timer->id, 'promptAt' => $prompt];
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/time-entries/confirm-after-hours', $body)->assertStatus(409);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/time-entries/confirm-after-hours', $body)->assertOk();
        $this->assertNull($timer->fresh()->after_hours_prompt_at);
        $this->travel(59)->minutes();
        $this->artisan('time-entries:check-after-hours')->assertSuccessful();
        $this->assertNull($timer->fresh()->after_hours_prompt_at);
        $this->travel(1)->minutes();
        $this->artisan('time-entries:check-after-hours')->assertSuccessful();
        $newPrompt = $timer->fresh()->after_hours_prompt_at->toIso8601String();
        $this->postJson('/api/v1/time-entries/confirm-after-hours', $body)->assertStatus(409);
        $this->travel(10)->minutes();
        $this->postJson('/api/v1/time-entries/confirm-after-hours', ['timeEntryId' => $timer->id, 'promptAt' => $newPrompt])->assertStatus(409);
    }
    public function test_policy_controls_grace_and_reminder_interval_and_keeps_the_prompt_grace(): void
    {
        Event::fake([NotificationCreated::class, TimerUpdated::class]);
        Bus::fake([SendWebPush::class]);
        $this->travelTo(now()->startOfDay()->setTime(18, 0));
        HrPolicy::create(['settings' => ['afterHoursIntervalMinutes' => 30, 'afterHoursGraceMinutes' => 5], 'effective_from' => today()]);
        $user = User::factory()->create(['is_active' => true, 'work_start_time' => '10:00:00', 'work_hours_per_day' => 8]);
        $task = Task::factory()->create();
        $timer = TimeEntry::factory()->active()->create(['user_id' => $user->id, 'trackable_type' => Task::class, 'trackable_id' => $task->id, 'start_time' => now()->setTime(10, 0)]);
        $this->artisan('time-entries:check-after-hours')->assertSuccessful();
        $prompt = $timer->fresh()->after_hours_prompt_at;
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/time-entries/active')->assertOk()->assertJsonPath('data.afterHoursGraceMinutes', 5);
        $this->postJson('/api/v1/time-entries/confirm-after-hours', ['timeEntryId' => $timer->id, 'promptAt' => $prompt->toIso8601String()])->assertOk();
        $this->travel(30)->minutes();
        $this->artisan('time-entries:check-after-hours')->assertSuccessful();
        $prompt = $timer->fresh()->after_hours_prompt_at;
        $this->assertNotNull($prompt);
        HrPolicy::create(['settings' => ['afterHoursIntervalMinutes' => 30, 'afterHoursGraceMinutes' => 60], 'effective_from' => today()]);
        $this->travel(5)->minutes();
        $this->artisan('time-entries:check-after-hours')->assertSuccessful();
        $this->assertTrue($timer->fresh()->end_time->equalTo($prompt));
    }

}
