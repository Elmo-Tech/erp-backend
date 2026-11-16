<?php

namespace Tests\Feature;

use App\Domain\Auth\MfaService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RoleBoundariesTest extends TestCase
{
    use RefreshDatabase;

    private function companies(): array
    {
        DB::table('currencies')->insert([
            ['code' => 'EGP', 'name' => 'Pound'], ['code' => 'EUR', 'name' => 'Euro'],
        ]);
        $ids = [(string) Str::uuid7(), (string) Str::uuid7()];
        foreach ($ids as $i => $id) {
            $eg = $i === 0;
            DB::table('companies')->insert([
                'id' => $id, 'code' => $eg ? 'EG' : 'IT',
                'legal_name' => $eg ? 'Elmo Test Egypt' : 'Elmo Test Italy',
                'country_code' => $eg ? 'EG' : 'IT', 'base_currency_code' => $eg ? 'EGP' : 'EUR',
                'timezone' => $eg ? 'Africa/Cairo' : 'Europe/Rome',
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return $ids;
    }

    private function membership(User $user, string $companyId, string $role): string
    {
        $id = (string) Str::uuid7();
        DB::table('company_memberships')->insert([
            'id' => $id, 'company_id' => $companyId, 'user_id' => $user->id,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('company_role_assignments')->insert([
            'id' => (string) Str::uuid7(), 'company_id' => $companyId,
            'membership_id' => $id, 'role_key' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function loginAs(User $user): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'correct-password',
        ])->assertStatus(202);
        $codes = (new MfaService())->issueRecoveryCodes($user);
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertOk();
    }

    public function test_egypt_and_italy_managers_are_denied_opposite_company_in_both_directions(): void
    {
        [$egypt, $italy] = $this->companies();
        foreach ([[$egypt, $italy], [$italy, $egypt]] as [$own, $other]) {
            $manager = User::factory()->create(['password' => 'correct-password']);
            $manager->mfa_secret = (new MfaService())->makeSecret();
            $manager->save();
            $this->membership($manager, $own, 'company_manager');
            $this->loginAs($manager);
            $this->postJson('/api/v1/companies/switch', ['company_id' => $other])->assertForbidden();
            $this->postJson('/api/v1/companies/switch', ['company_id' => $own])->assertOk();
            $this->getJson('/api/v1/companies/authorize/finance.view')->assertOk();
            $this->getJson('/api/v1/companies/authorize/payroll.view')->assertForbidden();
            $this->getJson('/api/v1/companies/consolidation/access')->assertForbidden();
            $this->postJson('/api/v1/auth/logout')->assertOk();
        }
    }

    public function test_external_accountant_and_role_revocation_fail_closed_inside_active_session(): void
    {
        [$egypt, $italy] = $this->companies();
        $accountant = User::factory()->create(['password' => 'correct-password']);
        $accountant->mfa_secret = (new MfaService())->makeSecret();
        $accountant->save();
        $member = $this->membership($accountant, $italy, 'external_accountant');
        $this->loginAs($accountant);
        $this->postJson('/api/v1/companies/switch', ['company_id' => $italy])->assertOk();
        $this->getJson('/api/v1/companies/authorize/finance.export')->assertOk();
        $this->getJson('/api/v1/companies/authorize/payroll.view')->assertForbidden();
        $this->postJson('/api/v1/companies/switch', ['company_id' => $egypt])->assertForbidden();
        DB::table('company_role_assignments')->where('membership_id', $member)->delete();
        $this->getJson('/api/v1/companies/authorize/finance.export')->assertForbidden();
        DB::table('company_memberships')->where('id', $member)->update(['status' => 'revoked']);
        $this->getJson('/api/v1/companies/context')->assertForbidden();
    }

    public function test_central_overview_and_consolidation_permission_are_explicit_and_separate(): void
    {
        [$egypt, $italy] = $this->companies();
        $central = User::factory()->create(['password' => 'correct-password']);
        $central->mfa_secret = (new MfaService())->makeSecret();
        $central->save();
        $global = (string) Str::uuid7();
        DB::table('global_role_assignments')->insert([
            'id' => $global, 'user_id' => $central->id,
            'role_key' => 'central_management', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->loginAs($central);
        $this->getJson('/api/v1/companies')->assertJsonCount(2, 'companies');
        $this->postJson('/api/v1/companies/switch', ['company_id' => $egypt])->assertOk();
        $this->postJson('/api/v1/companies/switch', ['company_id' => $italy])->assertOk();
        $this->getJson('/api/v1/companies/authorize/finance.view')->assertForbidden();
        $this->getJson('/api/v1/companies/authorize/payroll.view')->assertForbidden();
        $this->getJson('/api/v1/companies/consolidation/access')->assertForbidden();

        DB::table('global_role_assignments')->insert([
            'id' => (string) Str::uuid7(), 'user_id' => $central->id,
            'role_key' => 'consolidation_viewer', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson('/api/v1/companies/consolidation/access')
            ->assertOk()->assertExactJson(['authorized' => true, 'permission' => 'consolidation.view'])
            ->assertDontSee('amount');
        $this->getJson('/api/v1/companies/authorize/finance.export')->assertForbidden();
        DB::table('global_role_assignments')->where('id', $global)->update(['status' => 'revoked']);
        $this->getJson('/api/v1/companies/consolidation/access')->assertForbidden();
        $this->getJson('/api/v1/companies/context')->assertForbidden();
    }

    public function test_project_member_promoted_to_privileged_role_must_reauthenticate_with_mfa(): void
    {
        [$egypt] = $this->companies();
        $member = User::factory()->create(['password' => 'correct-password']);
        $membership = $this->membership($member, $egypt, 'project_member');
        $this->postJson('/api/v1/auth/login', [
            'email' => $member->email, 'password' => 'correct-password',
        ])->assertOk();
        $this->postJson('/api/v1/companies/switch', ['company_id' => $egypt])->assertOk();
        DB::table('company_role_assignments')->insert([
            'id' => (string) Str::uuid7(), 'company_id' => $egypt,
            'membership_id' => $membership, 'role_key' => 'finance',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson('/api/v1/companies/authorize/finance.view')->assertForbidden();
        $this->getJson('/api/v1/auth/me')->assertForbidden();
    }
}
