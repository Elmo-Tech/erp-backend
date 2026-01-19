<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlexibleStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function can_update_task_status_flexibly()
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::TODO,
        ]);

        // Test direct transition from TODO to COMPLETED (skipping intermediate steps)
        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => 'completed',
                'reason' => 'Task completed directly'
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::COMPLETED,
        ]);
    }

    /** @test */
    public function can_update_project_status_flexibly()
    {
        $project = Project::factory()->create([
            'status' => ProjectStatus::DRAFT,
        ]);

        // Test direct transition from DRAFT to COMPLETED (skipping intermediate steps)
        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/projects/{$project->id}/status", [
                'status' => 'completed',
                'reason' => 'Project completed directly'
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::COMPLETED,
        ]);
    }

    /** @test */
    public function tracks_bug_bounce_when_task_moves_from_testing_to_in_progress()
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::TESTING,
            'reopen_count' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => 'in_progress',
                'reason' => 'Found bugs during testing'
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::IN_PROGRESS,
            'reopen_count' => 1, // Should increment bug bounce counter
        ]);
    }

    /** @test */
    public function validates_status_enum_values()
    {
        $task = Task::factory()->create();

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => 'invalid_status'
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function prevents_same_status_update()
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::COMPLETED,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => 'completed'
            ]);

        $response->assertOk();
        
        // Should return success but no actual change
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::COMPLETED,
        ]);
    }
}