<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Task\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Task\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'assigned_to' => User::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => TaskStatus::TODO,
            'estimated_hours' => $this->faker->randomFloat(2, 1, 40),
            'total_time_spent' => 0,
            'internal_reviewer_notes' => null,
            'reopen_count' => 0,
        ];
    }

    /**
     * Indicate that the task is in todo status.
     */
    public function todo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::TODO,
        ]);
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::IN_PROGRESS,
        ]);
    }

    /**
     * Indicate that the task is in review.
     */
    public function review(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::REVIEW,
        ]);
    }

    /**
     * Indicate that the task is in testing.
     */
    public function testing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::TESTING,
        ]);
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::COMPLETED,
        ]);
    }

    /**
     * Indicate that the task has been reopened multiple times.
     */
    public function withReopens(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'reopen_count' => $count,
        ]);
    }
}
