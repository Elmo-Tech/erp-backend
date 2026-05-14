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
}
