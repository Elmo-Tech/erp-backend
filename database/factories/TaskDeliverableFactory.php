<?php

namespace Database\Factories;

use App\Enums\DeliverableStatus;
use App\Modules\Settings\Models\User;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskDeliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskDeliverable>
 */
class TaskDeliverableFactory extends Factory
{
    protected $model = TaskDeliverable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'acceptance_criteria' => $this->faker->paragraph(),
            'status' => DeliverableStatus::PENDING,
            'submission_notes' => null,
            'review_notes' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'points_awarded' => 0,
            'is_bonus' => $this->faker->boolean(20), // 20% chance of being bonus
            'order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the deliverable is submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliverableStatus::SUBMITTED,
            'submission_notes' => $this->faker->paragraph(),
            'submitted_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the deliverable is under review.
     */
    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliverableStatus::UNDER_REVIEW,
            'submission_notes' => $this->faker->paragraph(),
            'submitted_at' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
            'reviewed_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the deliverable is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliverableStatus::APPROVED,
            'submission_notes' => $this->faker->paragraph(),
            'review_notes' => $this->faker->paragraph(),
            'submitted_at' => $this->faker->dateTimeBetween('-2 weeks', '-1 week'),
            'reviewed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'reviewed_by' => User::factory(),
            'points_awarded' => $this->faker->numberBetween(1, 10),
        ]);
    }

    /**
     * Indicate that the deliverable is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliverableStatus::REJECTED,
            'submission_notes' => $this->faker->paragraph(),
            'review_notes' => $this->faker->paragraph(),
            'submitted_at' => $this->faker->dateTimeBetween('-2 weeks', '-1 week'),
            'reviewed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'reviewed_by' => User::factory(),
            'points_awarded' => -$this->faker->numberBetween(1, 5),
        ]);
    }

    /**
     * Indicate that the deliverable is a bonus deliverable.
     */
    public function bonus(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bonus' => true,
        ]);
    }

    /**
     * Indicate that the deliverable is a regular deliverable.
     */
    public function regular(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bonus' => false,
        ]);
    }
}
