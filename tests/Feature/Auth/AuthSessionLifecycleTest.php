<?php

namespace Tests\Feature\Auth;

use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuthSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_log_in_while_active_user_can(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password'),
            'is_active' => false,
        ]);

        User::factory()->create([
            'email' => 'active@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'active@example.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_inactive_user_cannot_use_or_refresh_an_existing_token(): void
    {
        $user = User::factory()->create([
            'email' => 'revoked@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $authToken = $this->cookieValue($loginResponse, 'auth_token');
        $csrfToken = $this->cookieValue($loginResponse, 'csrf_token');

        $user->update(['is_active' => false]);
        $this->assertNotNull(PersonalAccessToken::findToken($authToken));

        auth()->forgetGuards();

        $this->withHeader('Cookie', 'auth_token='.rawurlencode($authToken))
            ->getJson('/api/v1/auth/profile')
            ->assertUnauthorized();

        auth()->forgetGuards();

        $this->withToken($authToken)
            ->getJson('/api/v1/auth/profile')
            ->assertUnauthorized();

        auth()->forgetGuards();

        $this->withHeader('Cookie', $this->cookieHeader($authToken, $csrfToken))
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_deactivating_a_user_revokes_all_persisted_tokens(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $permission = Permission::findOrCreate('manage_users', 'web');
        $manager->givePermissionTo($permission);
        $manager->givePermissionTo(Permission::findOrCreate('toggle_user_status', 'web'));

        $target = User::factory()->create(['is_active' => true]);
        $target->createToken('browser');
        $target->createToken('mobile');

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/admin/users/manage/{$target->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('data.user.isActive', false);

        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_user_update_deactivation_revokes_all_persisted_tokens(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $permission = Permission::findOrCreate('manage_users', 'web');
        $manager->givePermissionTo($permission);
        $manager->givePermissionTo(Permission::findOrCreate('toggle_user_status', 'web'));

        $target = User::factory()->create(['is_active' => true]);
        $target->createToken('browser');

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/admin/users/manage/{$target->id}", [
            'isActive' => false,
        ])->assertOk();

        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_user_password_reset_revokes_all_persisted_tokens(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $permission = Permission::findOrCreate('manage_users', 'web');
        $manager->givePermissionTo($permission);
        $manager->givePermissionTo(Permission::findOrCreate('reset_user_credentials', 'web'));

        $target = User::factory()->create(['is_active' => true]);
        $target->createToken('browser');

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/admin/users/manage/{$target->id}", [
            'password' => 'ResetPassword3#',
            'password_confirmation' => 'ResetPassword3#',
        ])->assertOk();

        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_password_change_invalidates_old_sessions_and_rotates_current_session(): void
    {
        $user = User::factory()->create([
            'email' => 'password-change@example.com',
            'password' => bcrypt('OldPassword1!'),
            'is_active' => true,
        ]);

        $currentLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'OldPassword1!',
        ])->assertOk();
        $currentToken = $this->cookieValue($currentLogin, 'auth_token');
        $csrfToken = $this->cookieValue($currentLogin, 'csrf_token');

        $otherLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'OldPassword1!',
        ])->assertOk();
        $otherToken = $this->cookieValue($otherLogin, 'auth_token');

        auth()->forgetGuards();

        $changeResponse = $this
            ->withHeader('Cookie', $this->cookieHeader($currentToken, $csrfToken))
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'OldPassword1!',
                'new_password' => 'NewPassword2@',
                'new_password_confirmation' => 'NewPassword2@',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed successfully');

        $rotatedToken = $this->cookieValue($changeResponse, 'auth_token');

        $this->assertNull(PersonalAccessToken::findToken($currentToken));
        $this->assertNull(PersonalAccessToken::findToken($otherToken));
        $this->assertNotNull(PersonalAccessToken::findToken($rotatedToken));

        auth()->forgetGuards();

        $this->withHeader('Cookie', 'auth_token='.rawurlencode($otherToken))
            ->getJson('/api/v1/auth/profile')
            ->assertUnauthorized();

        auth()->forgetGuards();

        $this->withHeader('Cookie', 'auth_token='.rawurlencode($rotatedToken))
            ->getJson('/api/v1/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.profile.email', $user->email);
    }

    public function test_cross_site_browser_login_is_rejected_while_trusted_origin_login_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'origin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->withHeaders([
            'Origin' => 'https://attacker.example',
            'Sec-Fetch-Site' => 'cross-site',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(419);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->post('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(419);

        $this->withHeaders([
            'Origin' => (string) config('app.frontend_url'),
            'Sec-Fetch-Site' => 'same-site',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
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
