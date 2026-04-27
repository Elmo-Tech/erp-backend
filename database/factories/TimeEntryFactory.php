<?php

namespace Database\Factories;

use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TimeEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('-1 week', 'now');
        $endTime = $this->faker->dateTimeBetween($startTime, 'now');
        $durationMinutes = (int) (($endTime->getTimestamp() - $startTime->getTimestamp()) / 60);

        return [
            'user_id' => User::factory(),
            'trackable_id' => 1, // Will be overridden in tests
            'trackable_type' => 'App\\Models\\Lead', // Will be overridden in tests
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
        ];
    }

    /**
     * Create an active time entry (no end time).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_time' => null,
            'duration_minutes' => null,
        ]);
    }
}
