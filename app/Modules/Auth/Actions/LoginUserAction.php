<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Settings\Models\User;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class LoginUserAction
{
    public function execute(array $credentials): User
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->is_active) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        return $user;
    }
}
