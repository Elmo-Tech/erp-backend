<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'quotation_id' => null,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => ProjectStatus::DRAFT,
            'previous_status' => null,
            'budget' => $this->faker->randomFloat(2, 1000, 100000),
            'deadline' => $this->faker->dateTimeBetween('+1 week', '+6 months'),
            'ui_approved_at' => null,
            'auto_approval_job_id' => null,
        ];
    }

    /**
     * Indicate that the project is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::DRAFT,
        ]);
    }

    /**
     * Indicate that the project is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the project is in UI/UX phase.
     */
    public function uiUx(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::UI_UX,
        ]);
    }

    /**
     * Indicate that the project is in development.
     */
    public function development(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::DEVELOPMENT,
        ]);
    }

    /**
     * Indicate that the project is in testing.
     */
    public function testing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::TESTING,
        ]);
    }

    /**
     * Indicate that the project is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::COMPLETED,
        ]);
    }

    /**
     * Indicate that the project is on hold.
     */
    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::ON_HOLD,
        ]);
    }

    /**
     * Indicate that the project is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::CANCELLED,
        ]);
    }
}
