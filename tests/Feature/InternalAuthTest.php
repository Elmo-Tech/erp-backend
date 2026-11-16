<?php

namespace Tests\Feature;

use App\Domain\Auth\MfaService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InternalAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ]);
    }

    public function test_guest_cannot_reach_protected_company_or_identity_routes(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/companies')->assertUnauthorized();
        $this->getJson('/api/v1/companies/context')->assertUnauthorized();
    }

    public function test_password_login_me_and_logout_with_active_user_only(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonPath('message', 'Invalid credentials.');
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertOk()->assertJsonPath('user.id', $user->id);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.email', $user->email);
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $user->update(['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertUnprocessable()->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_privileged_account_cannot_bypass_mfa_and_recovery_is_single_use(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        DB::table('global_role_assignments')->insert([
            'id' => (string) Str::uuid7(), 'user_id' => $user->id,
            'role_key' => 'central_management', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mfa = new MfaService();
        $secret = $mfa->makeSecret();
        $user->mfa_secret = $secret;
        $user->save();
        $codes = $mfa->issueRecoveryCodes($user);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertStatus(202)->assertJsonPath('status', 'mfa_required');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => 'not-valid'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('global_management', true);
        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertStatus(202);
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertUnprocessable();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_company_switch_requires_membership_and_role_scope(): void
    {
        [$egypt, $italy] = $this->companies();
        $user = User::factory()->create(['password' => 'correct-password']);
        $membership = (string) Str::uuid7();
        DB::table('company_memberships')->insert([
            'id' => $membership, 'user_id' => $user->id, 'company_id' => $egypt,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('company_role_assignments')->insert([
            'id' => (string) Str::uuid7(), 'membership_id' => $membership,
            'company_id' => $egypt, 'role_key' => 'project_member',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertOk();
        $this->postJson('/api/v1/companies/switch', ['company_id' => $italy])->assertForbidden();
        $this->postJson('/api/v1/companies/switch', ['company_id' => $egypt])->assertOk();
        $this->getJson('/api/v1/companies/authorize/projects.view')->assertOk();
        $this->getJson('/api/v1/companies/authorize/finance.view')->assertForbidden();
        $this->getJson('/api/v1/companies/authorize/payroll.view')->assertForbidden();

        DB::table('company_memberships')->where('id', $membership)->update(['status' => 'revoked']);
        $this->getJson('/api/v1/companies/context')->assertForbidden();
        $this->getJson('/api/v1/companies/authorize/projects.view')->assertForbidden();
    }

    public function test_external_accountant_cannot_read_payroll_or_other_company(): void
    {
        [$egypt, $italy] = $this->companies();
        $user = User::factory()->create(['password' => 'correct-password']);
        $membership = (string) Str::uuid7();
        DB::table('company_memberships')->insert([
            'id' => $membership, 'user_id' => $user->id, 'company_id' => $italy,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('company_role_assignments')->insert([
            'id' => (string) Str::uuid7(), 'membership_id' => $membership,
            'company_id' => $italy, 'role_key' => 'external_accountant',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mfa = new MfaService();
        $user->mfa_secret = $mfa->makeSecret();
        $user->save();
        $codes = $mfa->issueRecoveryCodes($user);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertStatus(202);
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertOk();
        $this->postJson('/api/v1/companies/switch', ['company_id' => $egypt])->assertForbidden();
        $this->postJson('/api/v1/companies/switch', ['company_id' => $italy])->assertOk();
        $this->getJson('/api/v1/companies/authorize/finance.export')->assertOk();
        $this->getJson('/api/v1/companies/authorize/payroll.view')->assertForbidden();
    }

    /** @return array{string,string} */
    private function companies(): array
    {
        DB::table('currencies')->insert([
            ['code' => 'EGP', 'name' => 'Pound', 'minor_units' => 2, 'active' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'minor_units' => 2, 'active' => true],
        ]);
        $egypt = (string) Str::uuid7();
        $italy = (string) Str::uuid7();
        foreach ([$egypt => ['EG', 'EGP', 'Africa/Cairo'], $italy => ['IT', 'EUR', 'Europe/Rome']] as $id => [$country, $currency, $tz]) {
            DB::table('companies')->insert([
                'id' => $id, 'code' => 'TEST-'.$country, 'legal_name' => 'Test '.$country,
                'country_code' => $country, 'base_currency_code' => $currency,
                'timezone' => $tz, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return [$egypt, $italy];
    }
}
