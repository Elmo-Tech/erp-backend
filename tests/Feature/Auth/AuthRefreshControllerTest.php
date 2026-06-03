<?php

namespace Tests\Feature\Auth;

use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthRefreshControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_refresh_access_token(): void
    {
        User::factory()->create([
            'email' => 'refresh@example.com',
            'password' => bcrypt('password'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'refresh@example.com',
            'password' => 'password',
        ])->assertOk();

        $oldToken = $loginResponse->json('data.tokenDetails.token');

        $refreshResponse = $this
            ->withHeader('Authorization', 'Bearer '.$oldToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonPath('message', 'Token refreshed successfully');

        $newToken = $refreshResponse->json('data.tokenDetails.token');

        $this->assertNotEmpty($newToken);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertNull(PersonalAccessToken::findToken($oldToken));
        $this->assertNotNull(PersonalAccessToken::findToken($newToken));

        auth()->forgetGuards();

        $this
            ->withHeader('Authorization', 'Bearer '.$oldToken)
            ->getJson('/api/v1/auth/profile')
            ->assertUnauthorized();

        auth()->forgetGuards();

        $this
            ->withHeader('Authorization', 'Bearer '.$newToken)
            ->getJson('/api/v1/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.profile.email', 'refresh@example.com');
    }
}
