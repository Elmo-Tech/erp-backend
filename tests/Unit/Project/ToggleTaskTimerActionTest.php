<?php

namespace Tests\Unit\Project;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\Project\Actions\ToggleTaskTimerAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ToggleTaskTimerActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_timer_auto_checks_in_user_once_for_today(): void
    {
        Carbon::setTestNow('2026-05-13 09:05:00');

        $user = User::factory()->create();
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Implement attendance hook',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);

        $entry = app(ToggleTaskTimerAction::class)->execute($task, $user);

        $this->assertNull($entry->end_time);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-05-13 00:00:00',
            'clock_in' => '2026-05-13 09:05:00',
        ]);

        app(ToggleTaskTimerAction::class)->execute($task, $user);
        app(ToggleTaskTimerAction::class)->execute($task, $user);

        $this->assertSame(1, Attendance::where('user_id', $user->id)->whereDate('date', '2026-05-13')->count());

        Carbon::setTestNow();
    }

    public function test_starting_timer_for_user_closes_their_other_open_timer(): void
    {
        Carbon::setTestNow('2026-05-13 10:00:00');

        $user = User::factory()->create();
        $project = Project::factory()->create();
        $firstTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'First task',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);
        $secondTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Second task',
            'status' => 'in_progress',
            'assignee_id' => $user->id,
        ]);

        $action = app(ToggleTaskTimerAction::class);
        $firstEntry = $action->startForUser($firstTask, $user);

        Carbon::setTestNow('2026-05-13 10:25:00');
        $secondEntry = $action->startForUser($secondTask, $user);

        $this->assertNotNull($firstEntry->fresh()->end_time);
        $this->assertSame(25, $firstEntry->fresh()->duration_minutes);
        $this->assertNull($secondEntry->fresh()->end_time);

        Carbon::setTestNow();
    }
}
