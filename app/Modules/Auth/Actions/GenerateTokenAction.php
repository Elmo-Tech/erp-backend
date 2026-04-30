<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Settings\Models\User;

class GenerateTokenAction
{
    /**
     * @return array{token: string, expiresIn: int}
     */
    public function execute(User $user, string $name = 'auth-token'): array
    {
        return [
            'token' => $user->createToken($name)->plainTextToken,
            'expiresIn' => (int) config('sanctum.expiration', 525600),
        ];
    }
}
