<?php

namespace Database\Factories;

use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskTestScenario;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Task\Models\TaskTestScenario>
 */
class TaskTestScenarioFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TaskTestScenario::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'expected_result' => $this->faker->sentence(),
            'status' => 'pending',
            'order' => $this->faker->numberBetween(1, 10),
            'completed_by' => null,
            'completed_at' => null,
            'verified_by' => null,
            'verified_at' => null,
            'reviewer_notes' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the test scenario is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'passed',
            'completed_by' => User::factory(),
            'completed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the test scenario is verified as passed.
     */
    public function verifiedPassed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'passed',
            'completed_by' => User::factory(),
            'completed_at' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
            'verified_by' => User::factory(),
            'verified_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'reviewer_notes' => $this->faker->optional()->sentence(),
        ]);
    }

    /**
     * Indicate that the test scenario is verified as failed.
     */
    public function verifiedFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed_by' => User::factory(),
            'completed_at' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
            'verified_by' => User::factory(),
            'verified_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'reviewer_notes' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the test scenario is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'completed_by' => null,
            'completed_at' => null,
            'verified_by' => null,
            'verified_at' => null,
            'reviewer_notes' => null,
        ]);
    }
}
