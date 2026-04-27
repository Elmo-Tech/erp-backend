<?php

namespace Database\Factories;

use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskAssignee;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Task\Models\TaskAssignee>
 */
class TaskAssigneeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TaskAssignee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement(['developer', 'reviewer', 'lead']),
            'is_primary' => $this->faker->boolean(70), // 70% chance of being primary
            'points_earned' => 0,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the assignee is primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
            'role' => 'developer',
        ]);
    }

    /**
     * Indicate that the assignee is secondary.
     */
    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => false,
            'role' => $this->faker->randomElement(['reviewer', 'lead']),
        ]);
    }

    /**
     * Indicate that the assignee is a developer.
     */
    public function developer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'developer',
        ]);
    }

    /**
     * Indicate that the assignee is a reviewer.
     */
    public function reviewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'reviewer',
            'is_primary' => false,
        ]);
    }

    /**
     * Indicate that the assignee is a lead.
     */
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'lead',
            'is_primary' => false,
        ]);
    }
}
