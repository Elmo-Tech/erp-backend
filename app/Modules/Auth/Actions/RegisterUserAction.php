<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Settings\Models\User;

class RegisterUserAction
{
    public function execute(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->assignRole('user');

        return $user->load('roles');
    }
}
