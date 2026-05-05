<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Invoice\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_type' => Project::class,
            'model_id' => Project::factory(),
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'amount' => $this->faker->randomFloat(2, 100, 50000),
            'currency' => 'USD',
            'status' => InvoiceStatus::DRAFT,
            'due_date' => $this->faker->dateTimeBetween('+1 week', '+2 months'),
            'paid_at' => null,
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }

    /**
     * Indicate that the invoice is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::DRAFT,
        ]);
    }

    /**
     * Indicate that the invoice is sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::SENT,
        ]);
    }

    /**
     * Indicate that the invoice is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::PAID,
            'paid_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the invoice is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::OVERDUE,
            'due_date' => $this->faker->dateTimeBetween('-2 months', '-1 day'),
        ]);
    }

    /**
     * Indicate that the invoice is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::CANCELLED,
        ]);
    }
}
