<?php

namespace Tests\Unit\Project;

use App\Modules\Project\Actions\ProjectHistoryAction;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectHistoryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_change_stores_backed_enum_values(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user);

        $history = (new ProjectHistoryAction)->recordChange(
            $project,
            'task_status_changed',
            ExecutionTaskStatus::TO_DO,
            ExecutionTaskStatus::IN_PROGRESS,
        );

        $this->assertSame('to_do', $history->old_value);
        $this->assertSame('in_progress', $history->new_value);
    }
}
