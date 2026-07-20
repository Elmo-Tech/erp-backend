<?php

namespace Tests\Feature\Auth;

use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class AuthCookieSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_http_only_auth_cookie_and_does_not_return_token_in_json(): void
    {
        User::factory()->create([
            'email' => 'secure@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'secure@example.com',
            'password' => 'password',
        ])->assertOk();

        $response->assertJsonMissingPath('data.tokenDetails.token');
        $response->assertJsonPath('data.tokenDetails.expiresIn', (int) config('sanctum.expiration', 525600));

        $authCookie = $this->cookie($response, 'auth_token');
        $csrfCookie = $this->cookie($response, 'csrf_token');

        $this->assertTrue($authCookie->isHttpOnly());
        $this->assertFalse($csrfCookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $authCookie->getSameSite()));
        $this->assertNotEmpty($authCookie->getValue());
        $this->assertNotEmpty($csrfCookie->getValue());
    }

    public function test_cookie_authenticated_mutations_require_matching_csrf_header(): void
    {
        User::factory()->create([
            'email' => 'csrf@example.com',
            'password' => bcrypt('password'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'csrf@example.com',
            'password' => 'password',
        ])->assertOk();

        $authToken = $this->cookie($loginResponse, 'auth_token')->getValue();
        $csrfToken = $this->cookie($loginResponse, 'csrf_token')->getValue();
        $this->assertNotNull(PersonalAccessToken::findToken(rawurldecode((string) $authToken)));

        $this
            ->withHeader('Cookie', $this->cookieHeader($authToken, $csrfToken))
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(419);

        $this
            ->withHeader('Cookie', $this->cookieHeader($authToken, $csrfToken))
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }

    private function cookie($response, string $name): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        $this->fail("Cookie {$name} was not set.");
    }

    private function cookieHeader(string $authToken, string $csrfToken): string
    {
        return 'auth_token='.rawurlencode($authToken).'; csrf_token='.rawurlencode($csrfToken);
    }
}
