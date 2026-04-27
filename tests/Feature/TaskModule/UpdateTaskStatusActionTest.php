<?php

namespace Tests\Feature\TaskModule;

use App\Enums\TaskStatus;
use App\Models\User;
use App\Modules\Task\Actions\UpdateTaskStatusAction;
use App\Modules\Task\Models\Task;
use App\Services\Tasks\TaskNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UpdateTaskStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_status_and_records_kanban_activity_without_changing_api_contract_models(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $task = Task::query()->create([
            'title' => 'Move card',
            'status' => TaskStatus::TODO,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $action = new UpdateTaskStatusAction(app(TaskNotificationService::class));

        $updated = $action->execute($task, TaskStatus::IN_PROGRESS->value, $user, 'Kanban drag');

        $this->assertTrue($updated->is(Task::query()->findOrFail($task->id)));
        $this->assertSame(TaskStatus::IN_PROGRESS, $updated->status);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => 'status_change',
        ]);
    }
}
