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

        $oldToken = $this->cookieValue($loginResponse, 'auth_token');
        $csrfToken = $this->cookieValue($loginResponse, 'csrf_token');

        $refreshResponse = $this
            ->withHeader('Cookie', $this->cookieHeader($oldToken, $csrfToken))
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonPath('message', 'Token refreshed successfully');

        $newToken = $this->cookieValue($refreshResponse, 'auth_token');

        $this->assertNotEmpty($newToken);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertNull(PersonalAccessToken::findToken($oldToken));
        $this->assertNotNull(PersonalAccessToken::findToken($newToken));

        auth()->forgetGuards();

        $this
            ->withHeader('Cookie', 'auth_token='.rawurlencode($oldToken))
            ->getJson('/api/v1/auth/profile')
            ->assertUnauthorized();

        auth()->forgetGuards();

        $this
            ->withHeader('Cookie', 'auth_token='.rawurlencode($newToken))
            ->getJson('/api/v1/auth/profile')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.profile.email', 'refresh@example.com');
    }

    private function cookieValue($response, string $name): string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return (string) $cookie->getValue();
            }
        }

        $this->fail("Cookie {$name} was not set.");
    }

    private function cookieHeader(string $authToken, string $csrfToken): string
    {
        return 'auth_token='.rawurlencode($authToken).'; csrf_token='.rawurlencode($csrfToken);
    }
}
