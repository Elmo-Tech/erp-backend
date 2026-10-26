<?php

namespace Tests\Feature;

use App\Enums\TimerStopReason;
use App\Events\TimerUpdated;
use App\Events\WorkActivityChanged;
use App\Jobs\DeleteProjectUploads;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Projects\ProjectDeletionService;
use App\Services\Sales\TimeTrackingService;
use App\Services\Tasks\WorkActivityPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkMonitoringRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_subscription_requires_current_work_visibility_permissions(): void
    {
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb', [
            'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test-app',
            'options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ]);
        // Channel callbacks belong to the broadcaster selected at registration.
        require base_path('routes/channels.php');
        $payload = ['socket_id' => '123.456', 'channel_name' => 'private-work-monitoring'];
        $this->postJson('/api/broadcasting/auth', $payload)->assertUnauthorized();
        foreach ([
            [null, [], true, 403],
            [null, ['view_dashboard'], true, 403],
            ['admin', [], true, 403],
            ['admin', ['view_dashboard'], true, 200],
            ['admin', ['view_dashboard_overview'], true, 200],
            [null, ['view_all_time_entries'], true, 200],
            [null, ['view_all_time_entries'], false, 403],
        ] as [$role, $permissions, $active, $status]) {
            $user = User::factory()->create(['is_active' => $active]);
            if ($role) {
                $user->assignRole(Role::findOrCreate($role, 'web'));
            }
            foreach ($permissions as $permission) {
                $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
            }
            $this->actingAs($user, 'sanctum')->postJson('/api/broadcasting/auth', $payload)->assertStatus($status);
        }
    }

    public function test_signals_are_sent_only_after_commit_and_not_after_rollback(): void
    {
        Event::fake([TimerUpdated::class, WorkActivityChanged::class]);
        DB::beginTransaction();
        app(WorkActivityPublisher::class)->changed('project_deleted', [42, 42]);
        Event::assertNotDispatched(WorkActivityChanged::class);
        Event::assertNotDispatched(TimerUpdated::class);
        DB::commit();
        Event::assertDispatchedTimes(WorkActivityChanged::class, 1);
        Event::assertDispatchedTimes(TimerUpdated::class, 1);
        Event::assertDispatched(WorkActivityChanged::class, function ($event) {
            $this->assertSame('private-work-monitoring', $event->broadcastOn()[0]->name);
            $this->assertSame(['changeId', 'reason'], array_keys($event->broadcastWith()));

            return true;
        });
        DB::beginTransaction();
        app(WorkActivityPublisher::class)->changed('rolled_back', [42]);
        DB::rollBack();
        Event::assertDispatchedTimes(WorkActivityChanged::class, 1);
        Event::assertDispatchedTimes(TimerUpdated::class, 1);
    }

    public function test_all_timer_lifecycle_changes_notify_monitors_but_heartbeats_do_not(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        Event::fake([TimerUpdated::class, WorkActivityChanged::class]);
        $service = app(TimeTrackingService::class);
        $entry = $service->startTimer($user, $task);
        $count = Event::dispatched(WorkActivityChanged::class)->count();
        $service->heartbeat($user, $entry->id, $entry->session_id);
        Event::assertDispatchedTimes(WorkActivityChanged::class, $count);
        $paused = $service->pauseTimer($user);
        $service->resumeTimer($user, $paused);
        $held = $service->holdTimer($user, 'Waiting for assets');
        $service->resumeTimer($user, $held);
        $service->endTimer($user);
        $next = Task::factory()->create();
        $service->startTimer($user, $next);
        $service->stopTimer($user, TimerStopReason::ADMIN_STOP);
        $service->startTimer($user, $next);
        config()->set('time_tracking.expire_on_disconnect', true);
        $this->travel(5)->minutes();
        $this->assertSame(1, $service->expireStaleTimers());
        $reasons = Event::dispatched(WorkActivityChanged::class)->map(fn ($args) => $args[0]->reason)->all();
        foreach (['timer_started', 'timer_pause', 'timer_hold', 'timer_end', 'timer_admin_stop', 'timer_connection_lost'] as $reason) {
            $this->assertContains($reason, $reasons);
        }
        $this->assertSame(5, count(array_filter($reasons, fn ($reason) => $reason === 'timer_started')));
    }

    public function test_rolled_back_timer_start_does_not_broadcast_uncommitted_work(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        Event::fake([TimerUpdated::class, WorkActivityChanged::class]);
        DB::beginTransaction();
        $entry = app(TimeTrackingService::class)->startTimer($user, $task);
        Event::assertNotDispatched(TimerUpdated::class);
        Event::assertNotDispatched(WorkActivityChanged::class);
        DB::rollBack();
        $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
        Event::assertNotDispatched(TimerUpdated::class);
        Event::assertNotDispatched(WorkActivityChanged::class);
    }

    public function test_admin_time_corrections_resynchronize_the_owner_and_monitoring_views(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $actor->givePermissionTo(Permission::findOrCreate('edit_time_entries', 'web'));
        $task = Task::factory()->create();
        $entry = app(TimeTrackingService::class)->startTimer($owner, $task);
        Event::fake([TimerUpdated::class, WorkActivityChanged::class]);
        $this->actingAs($actor, 'sanctum')->putJson('/api/v1/time-entries/'.$entry->id, ['endTime' => now()->toISOString()])->assertOk();
        $this->assertNotNull($entry->fresh()->end_time);
        Event::assertDispatched(TimerUpdated::class, fn ($event) => $event->userId === $owner->id);
        Event::assertDispatched(WorkActivityChanged::class, fn ($event) => $event->reason === 'timer_edited');
    }

    public function test_deleting_project_notifies_monitors_and_each_affected_timer_owner(): void
    {
        Bus::fake([DeleteProjectUploads::class]);
        $task = Task::factory()->create();
        $owner = User::factory()->create();
        $pausedOwner = User::factory()->create();
        $unrelated = User::factory()->create();
        foreach ([$owner, $pausedOwner] as $user) {
            TimeEntry::factory()->create([
                'user_id' => $user->id, 'trackable_type' => Task::class, 'trackable_id' => $task->id,
                'end_time' => $user->is($owner) ? null : now(), 'stop_reason' => $user->is($owner) ? null : 'pause',
            ]);
        }
        TimeEntry::factory()->create(['user_id' => $unrelated->id, 'end_time' => null]);
        Event::fake([TimerUpdated::class, WorkActivityChanged::class]);
        app(ProjectDeletionService::class)->permanentlyDelete($task->project);
        Event::assertDispatched(WorkActivityChanged::class, fn ($event) => $event->reason === 'project_deleted');
        foreach ([$owner, $pausedOwner] as $user) {
            Event::assertDispatched(TimerUpdated::class, fn ($event) => $event->userId === $user->id && $event->action === 'targets_changed');
        }
        Event::assertNotDispatched(TimerUpdated::class, fn ($event) => $event->userId === $unrelated->id);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_deleted_restored_and_archived_targets_resynchronize_their_timer_owner(): void
    {
        $task = Task::factory()->create();
        $user = User::factory()->create();
        TimeEntry::factory()->create(['user_id' => $user->id, 'trackable_type' => Task::class, 'trackable_id' => $task->id, 'end_time' => null]);
        Event::fake([TimerUpdated::class, WorkActivityChanged::class]);
        $task->delete();
        $task->restore();
        $project = Project::findOrFail($task->project_id);
        $project->delete();
        $project->restore();
        $project->update(['title' => 'Renamed work project']);
        $task->update(['title' => 'Renamed work task']);
        Event::assertDispatchedTimes(TimerUpdated::class, 6);
        Event::assertDispatched(TimerUpdated::class, fn ($event) => $event->userId === $user->id);
        Event::assertDispatched(WorkActivityChanged::class, fn ($event) => $event->reason === 'task_targets_changed');
        Event::assertDispatched(WorkActivityChanged::class, fn ($event) => $event->reason === 'project_targets_changed');
    }

    public function test_failed_monitoring_delivery_does_not_undo_committed_changes(): void
    {
        $task = Task::factory()->create();
        $user = User::factory()->create();
        Broadcast::shouldReceive('event')->andThrow(new \RuntimeException('Reverb offline'));
        Log::spy();
        $entry = app(TimeTrackingService::class)->startTimer($user, $task);
        app(TimeTrackingService::class)->pauseTimer($user);
        $this->assertSame(TimerStopReason::PAUSE, $entry->fresh()->stop_reason);
        Log::shouldHaveReceived('warning')->with('Work changed but monitoring broadcast could not be sent', \Mockery::type('array'))->atLeast()->once();
    }
}
