<?php

namespace Tests\Feature;

use App\Jobs\SendWebPush;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskPushDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_task_assignment_schedules_a_push_for_the_recipient(): void
    {
        Bus::fake([SendWebPush::class]);
        $user = User::factory()->create(['is_active' => true]);
        $task = Task::factory()->create();
        Notification::sendNow($user, new TaskAssigned($task, 'Manager'), ['database']);
        $this->assertDatabaseCount('notifications', 1);
        Bus::assertDispatched(SendWebPush::class, fn ($job) => $job->userId === $user->id && $job->payload['task_id'] === $task->id);
    }
}
