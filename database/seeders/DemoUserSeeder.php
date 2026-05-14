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
                'name' => 'Mohamed Elhaddad',
                'email' => 'founder@elmotech.it',
                'role' => 'founder',
                'specialization' => UserSpecialization::FULLSTACK,
                'total_points' => 2400,
            ],
            [
                'name' => 'Mohamed Sougou',
                'email' => 's.mohamed@elmotech.it',
                'role' => 'pmo',
                'specialization' => UserSpecialization::FULLSTACK,
                'total_points' => 1780,
            ],
            [
                'name' => 'Mahmoud Saber',
                'email' => 'm.saber@elmotech.it',
                'role' => 'manager',
                'specialization' => UserSpecialization::FULLSTACK,
                'total_points' => 1780,
            ],
            [
                'name' => 'Hisham Atef',
                'email' => 'h.atef@elmotech.it',
                'role' => 'techdev',
                'specialization' => UserSpecialization::BACKEND,
                'total_points' => 1960,
            ],
            [
                'name' => 'Mohamed Hassan',
                'email' => 'm.hassan@elmotech.it',
                'role' => 'dev',
                'specialization' => UserSpecialization::FRONTEND,
                'total_points' => 1250,
            ],
            [
                'name' => 'Ashraf Mohamed',
                'email' => 'ashraf@elmotech.it',
                'role' => 'dev',
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
