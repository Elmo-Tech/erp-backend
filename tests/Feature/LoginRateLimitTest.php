<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_valid_admin_logins_do_not_exhaust_failed_login_allowance(): void
    {
        config(['rate_limits.failed_logins_per_minute' => 2, 'rate_limits.api_per_minute' => 1200]);
        $user = User::factory()->create(['password' => 'password123']);
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo(Permission::findOrCreate('view_admin_stats', 'web'));
        $user->assignRole($role);

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password123'])
                ->assertOk()->assertJsonPath('data.role', 'admin');
        }

        $this->withToken($response->json('data.tokenDetails.token'))
            ->getJson('/api/v1/admin/stats/overview')->assertOk();
    }

    public function test_failed_credentials_are_still_rate_limited(): void
    {
        config(['rate_limits.failed_logins_per_minute' => 2, 'rate_limits.api_per_minute' => 1200]);
        $user = User::factory()->create();
        $payload = ['email' => $user->email, 'password' => 'incorrect-password'];
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_api_limit_uses_the_configured_environment_budget(): void
    {
        config(['rate_limits.api_per_minute' => 2]);
        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/health')->assertStatus(429);
    }
}
