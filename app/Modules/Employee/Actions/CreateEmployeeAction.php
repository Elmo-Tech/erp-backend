<?php

namespace App\Modules\Employee\Actions;

use App\Modules\User\Models\User;

class CreateEmployeeAction
{
    public function execute(array $data): User
    {
        $employee = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_employee' => true,
            'is_active' => $data['isActive'] ?? true,
            'salary' => $data['salary'] ?? null,
            'work_hours_per_day' => $data['workHoursPerDay'] ?? 8,
            'work_start_time' => $data['workStartTime'] ?? '10:00:00',
            'grace_days_allowed' => $data['graceDaysAllowed'] ?? 5,
            'internal_hourly_cost' => $data['internalHourlyCost'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'skills' => $data['skills'] ?? [],
        ]);

        if (! empty($data['role'])) {
            $employee->assignRole($data['role']);
        }

        return $employee->load('roles');
    }
}
