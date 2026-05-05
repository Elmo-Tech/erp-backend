<?php

namespace Database\Seeders;

use App\Enums\UserSpecialization;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the default platform owner account.
     *
     * Permissions and roles are intentionally delegated to
     * RolesAndPermissionsSeeder so this file cannot drift into a second PBAC
     * source of truth.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        /** @var User $founder */
        $founder = User::updateOrCreate(
            ['email' => 'm.saber@elmotech.it'],
            [
                'name' => 'Mahmoud Saber',
                'password' => Hash::make('Mahmoud_2091.'),
                'email_verified_at' => now(),
                'is_active' => true,
                'is_employee' => true,
                'specialization' => UserSpecialization::FULLSTACK,
                'work_hours_per_day' => 8,
                'work_start_time' => '10:00:00',
                'grace_days_allowed' => 5,
                'grace_days_used' => 0,
                'internal_hourly_cost' => 0,
                'salary' => 0,
                'total_deductions' => 0,
                'bonus_base_amount' => 0,
            ]
        );

        $founder->syncRoles(['founder']);

        $this->command->info('Founder user seeded successfully.');
    }
}
