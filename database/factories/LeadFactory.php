<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Lead>
     */
    protected $model = Lead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'company' => $this->faker->company(),
            'source' => $this->faker->randomElement(LeadSource::cases()),
            'status' => $this->faker->randomElement(LeadStatus::cases()),
        ];
    }

    /**
     * Indicate that the lead is new.
     */
    public function newLead(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::NEW,
        ]);
    }

    /**
     * Indicate that the lead has been contacted.
     */
    public function contacted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::CONTACTED,
        ]);
    }

    /**
     * Indicate that the lead is qualified.
     */
    public function qualified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::QUALIFIED,
        ]);
    }

    /**
     * Indicate that the lead has won.
     */
    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::WON,
        ]);
    }
}
