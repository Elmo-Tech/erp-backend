<?php

namespace App\Modules\Auth\Actions;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class LoginUserAction
{
    public function execute(array $credentials): User
    {
        if (! Auth::attempt($credentials)) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
