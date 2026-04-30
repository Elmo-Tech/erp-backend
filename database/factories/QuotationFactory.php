<?php

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Modules\CRM\Models\Client;
use App\Modules\Quotation\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Quotation\Models\Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Quotation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $frontendHours = $this->faker->numberBetween(5, 50);
        $backendHours = $this->faker->numberBetween(10, 80);
        $uiUxHours = $this->faker->numberBetween(5, 30);
        $testingHours = $this->faker->numberBetween(5, 20);
        $pmHours = $this->faker->numberBetween(2, 15);

        $totalHours = $frontendHours + $backendHours + $uiUxHours + $testingHours + $pmHours;
        $hourlyRate = $this->faker->numberBetween(50, 200);
        $discountPercentage = $this->faker->numberBetween(0, 25);

        $grossPrice = $totalHours * $hourlyRate;
        $discountAmount = $grossPrice * ($discountPercentage / 100);
        $finalPrice = $grossPrice - $discountAmount;

        return [
            'client_id' => Client::factory(),
            'estimations' => [
                'frontend_hours' => $frontendHours,
                'backend_hours' => $backendHours,
                'ui_ux_hours' => $uiUxHours,
                'testing_hours' => $testingHours,
                'pm_hours' => $pmHours,
            ],
            'hourly_rate' => $hourlyRate,
            'discount_percentage' => $discountPercentage,
            'final_price' => round($finalPrice, 2),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'SAR']),
            'status' => $this->faker->randomElement(QuotationStatus::cases()),
        ];
    }

    /**
     * Indicate that the quotation is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::DRAFT,
        ]);
    }

    /**
     * Indicate that the quotation has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::SENT,
        ]);
    }

    /**
     * Indicate that the quotation has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::ACCEPTED,
        ]);
    }

    /**
     * Indicate that the quotation has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::REJECTED,
        ]);
    }

    /**
     * Create a quotation for a specific client.
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }
}
