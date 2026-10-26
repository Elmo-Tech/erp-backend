<?php

namespace Tests\Feature;

use App\Events\TimerUpdated;
use App\Jobs\DeleteProjectUploads;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Sales\TimeTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeploymentRegressionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_accepts_the_actual_ui_payload_with_empty_confirmation(): void
    {
        $actor = $this->projectAdministrator();
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $this->actingAs($actor, 'sanctum')->deleteJson('/api/v1/projects/'.$project->id, ['permanent' => false, 'confirmation' => ''])->assertOk();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->deleteJson('/api/v1/projects/'.$project->id.'/permanent', ['confirmation' => ''])->assertUnprocessable();
        $guarded = Project::factory()->create();
        $deletedTask = Task::factory()->create(['project_id' => $guarded->id]);
        $deletedTask->delete();
        TimeEntry::factory()->create(['user_id' => $actor->id, 'trackable_type' => Task::class, 'trackable_id' => $deletedTask->id, 'end_time' => null]);
        $this->deleteJson('/api/v1/projects/'.$guarded->id, ['permanent' => false])->assertStatus(409);
        $this->assertNotSoftDeleted('projects', ['id' => $guarded->id]);
    }

    public function test_permanent_deletion_handles_text_notification_payloads_and_preserves_unrelated_or_malformed_records(): void
    {
        Bus::fake([DeleteProjectUploads::class]);
        $actor = $this->projectAdministrator();
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $payloads = [
            json_encode(['project_id' => $project->id]), json_encode(['projectId' => (string) $project->id]),
            json_encode(['task_id' => $task->id]), json_encode(['taskId' => (string) $task->id]),
            json_encode(['project_id' => $other->id]), 'legacy invalid payload',
        ];
        $ids = [];
        foreach ($payloads as $payload) {
            $ids[] = $id = (string) Str::uuid();
            DB::table('notifications')->insert(['id' => $id, 'type' => 'regression', 'notifiable_type' => User::class, 'notifiable_id' => $actor->id, 'data' => $payload]);
        }
        $this->actingAs($actor, 'sanctum')->deleteJson('/api/v1/projects/'.$project->id, ['permanent' => true, 'confirmation' => $project->title])->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        foreach (array_slice($ids, 0, 4) as $id) {
            $this->assertDatabaseMissing('notifications', ['id' => $id]);
        }
        $this->assertDatabaseHas('notifications', ['id' => $ids[4]]);
        $this->assertDatabaseHas('notifications', ['id' => $ids[5]]);
        $this->assertDatabaseHas('projects', ['id' => $other->id]);
    }

    public function test_dashboard_and_active_timer_sheet_include_the_same_open_archived_and_missing_work(): void
    {
        config()->set('simplification.enabled', true);
        Event::fake([TimerUpdated::class]);
        $admin = $this->projectAdministrator();
        $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
        $live = Task::factory()->create();
        $archived = Task::factory()->create();
        $archived->project->delete();
        $deleted = Task::factory()->create();
        $deleted->delete();
        foreach ([$live->id, $archived->id, $deleted->id, 999999] as $taskId) {
            TimeEntry::factory()->create(['trackable_type' => Task::class, 'trackable_id' => $taskId, 'end_time' => null]);
        }
        $sheet = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/time-entries/active-timers')->assertOk()->json('data');
        $dashboard = $this->getJson('/api/v1/dashboard/overview')->assertOk()->json('data.liveWork');
        $this->assertCount(4, $sheet);
        $this->assertSame(count($sheet), $dashboard['activeTimerCount']);
        $this->assertEqualsCanonicalizing(array_column($sheet, 'userId'), array_column($dashboard['working'], 'userId'));
        $this->assertContains('project_archived', array_column($dashboard['working'], 'targetStatus'));
        $this->assertContains('task_deleted', array_column($dashboard['working'], 'targetStatus'));
    }

    public static function unavailableTargets(): array
    {
        return [['project_archived'], ['task_deleted'], ['task_missing']];
    }

    #[DataProvider('unavailableTargets')]
    public function test_ending_unavailable_work_saves_time_without_completing_deleted_work(string $state): void
    {
        Event::fake([TimerUpdated::class]);
        $user = User::factory()->create();
        $task = Task::factory()->create(['is_pipeline_task' => true]);
        $entry = app(TimeTrackingService::class)->startTimer($user, $task);
        $this->travel(5)->minutes();
        $this->makeUnavailable($task, $state);
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/time-entries/end')->assertOk()
            ->assertJsonPath('data.isActive', false)->assertJsonPath('data.durationMinutes', 5);
        $this->assertStringContainsString('work time saved', $response->json('message'));
        $this->assertStringContainsString('not marked complete', $response->json('message'));
        $this->assertNotNull($entry->fresh()->end_time);
        if ($state !== 'task_missing') {
            $this->assertNull(Task::withTrashed()->findOrFail($task->id)->completed_at);
        }
        $this->postJson('/api/v1/time-entries/end')->assertStatus(409)->assertJsonPath('message', 'This timer is already stopped or no longer available. Refresh to see the latest timer status.');
    }

    #[DataProvider('unavailableTargets')]
    public function test_unavailable_paused_work_cannot_be_resumed_and_does_not_create_a_timer(string $state): void
    {
        Event::fake([TimerUpdated::class]);
        $user = User::factory()->create();
        $task = Task::factory()->create();
        $service = app(TimeTrackingService::class);
        $entry = $service->startTimer($user, $task);
        $service->pauseTimer($user);
        $this->makeUnavailable($task, $state);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/time-entries/'.$entry->id.'/resume')->assertUnprocessable();
        $this->assertDatabaseCount('time_entries', 1);
        $this->getJson('/api/v1/time-entries/resumable')->assertOk()->assertJsonPath('data', null);
    }

    public function test_realtime_outage_does_not_report_a_committed_start_as_a_failed_timer(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        Broadcast::shouldReceive('event')->andThrow(new \RuntimeException('Reverb offline'));
        Log::spy();
        $entry = app(TimeTrackingService::class)->startTimer($user, $task);
        $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'end_time' => null]);
        Log::shouldHaveReceived('warning')->with('Timer saved but realtime delivery failed', \Mockery::type('array'))->once();
    }

    private function makeUnavailable(Task $task, string $state): void
    {
        match ($state) {
            'project_archived' => $task->project->delete(),
            'task_deleted' => $task->delete(),
            'task_missing' => Task::whereKey($task->id)->forceDelete(),
        };
    }

    private function projectAdministrator(): User
    {
        $user = User::factory()->create();
        foreach (['view_dashboard', 'view_all_time_entries', 'view_all_projects', 'manage_projects', 'force_delete_projects'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        return $user;
    }
}
