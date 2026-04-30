<?php

namespace Database\Seeders;

use App\Enums\UserSpecialization;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Seed explicit demo users for the matrix dashboard roles.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Mariam Saber',
                'email' => 'founder@elmo.tech',
                'role' => 'founder',
                'specialization' => UserSpecialization::FULLSTACK,
                'total_points' => 2400,
            ],
            [
                'name' => 'Omar Nabil',
                'email' => 'pmo@elmo.tech',
                'role' => 'pmo',
                'specialization' => UserSpecialization::FULLSTACK,
                'total_points' => 1780,
            ],
            [
                'name' => 'Nadine Hassan',
                'email' => 'techlead@elmo.tech',
                'role' => 'tech_lead',
                'specialization' => UserSpecialization::BACKEND,
                'total_points' => 1960,
            ],
            [
                'name' => 'Youssef Ali',
                'email' => 'dev@elmo.tech',
                'role' => 'developer',
                'specialization' => UserSpecialization::FRONTEND,
                'total_points' => 1250,
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];

            /** @var User $user */
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'is_employee' => true,
                    'specialization' => $userData['specialization'],
                    'work_hours_per_day' => 8,
                    'work_start_time' => '09:00:00',
                    'grace_days_allowed' => 2,
                    'grace_days_used' => 0,
                    'total_points' => $userData['total_points'],
                    'internal_hourly_cost' => 35,
                    'salary' => 3000,
                    'total_deductions' => 0,
                    'bonus_base_amount' => 0,
                ]
            );

            $user->syncRoles([$role]);
        }
    }
}
