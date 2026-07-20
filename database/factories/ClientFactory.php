<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'company',
            'display_name' => $this->faker->company(),
            'legal_name' => $this->faker->company(),
            'primary_email' => $this->faker->unique()->safeEmail(),
            'primary_phone' => $this->faker->phoneNumber(),
            'company_name' => $this->faker->company(),
            'position' => $this->faker->jobTitle(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'website' => $this->faker->optional()->url(),
            'notes' => $this->faker->optional()->paragraph(),
            'lifecycle_stage' => 'client',
            'status' => 'active',
            'user_id' => null,
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the client is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the client is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
