<?php

namespace Database\Seeders;

use App\Modules\Settings\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the system-wide key-value settings required at first boot.
 *
 * Re-running is fully idempotent — existing values are NOT overwritten,
 * so production overrides survive a re-seed.
 *
 * Run with:
 *   php artisan db:seed --class=SystemSettingsSeeder
 */
class SystemSettingsSeeder extends Seeder
{
    /**
     * @var array<string, array{value: string, description: string}>
     */
    private array $defaults = [
        'gamification_point_value' => [
            'value'       => '50',
            'description' => 'Monetary value (in base currency) of a single gamification point. Used by the payroll engine to convert points_earned into points_bonus_amount.',
        ],
    ];

    public function run(): void
    {
        foreach ($this->defaults as $key => $meta) {
            // firstOrCreate — never overwrite a value that was intentionally
            // changed in production.
            Setting::firstOrCreate(
                ['key' => $key],
                [
                    'value'       => $meta['value'],
                    'description' => $meta['description'],
                ]
            );
        }

        $this->command->info('✅ System settings seeded (gamification_point_value = 50).');
    }
}
