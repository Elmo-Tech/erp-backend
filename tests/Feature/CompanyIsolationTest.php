<?php

namespace Tests\Feature;

use App\Domain\Companies\CompanyAccess;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_only_grants_access_to_its_active_company(): void
    {
        [$egypt, $italy] = $this->createTestCompanies();
        $user = User::factory()->create();
        $membershipId = (string) Str::uuid7();

        DB::table('company_memberships')->insert([
            'id' => $membershipId,
            'company_id' => $egypt,
            'user_id' => $user->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $access = new CompanyAccess();
        $this->assertTrue($access->userCanAccess($user->id, $egypt));
        $this->assertFalse($access->userCanAccess($user->id, $italy));

        DB::table('company_memberships')->where('id', $membershipId)->update(['status' => 'inactive']);
        $this->assertFalse($access->userCanAccess($user->id, $egypt));
    }

    public function test_composite_foreign_key_rejects_cross_company_role_assignment(): void
    {
        [$egypt, $italy] = $this->createTestCompanies();
        $user = User::factory()->create();
        $membershipId = (string) Str::uuid7();

        DB::table('company_memberships')->insert([
            'id' => $membershipId,
            'company_id' => $egypt,
            'user_id' => $user->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('company_role_assignments')->insert([
            'id' => (string) Str::uuid7(),
            'company_id' => $italy,
            'membership_id' => $membershipId,
            'role_key' => 'viewer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{string, string} */
    private function createTestCompanies(): array
    {
        DB::table('currencies')->insert([
            ['code' => 'EGP', 'name' => 'Egyptian pound', 'minor_units' => 2, 'active' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'minor_units' => 2, 'active' => true],
        ]);

        $egypt = (string) Str::uuid7();
        $italy = (string) Str::uuid7();
        $now = now();
        DB::table('companies')->insert([
            [
                'id' => $egypt,
                'code' => 'TEST-EG',
                'legal_name' => 'Test Egypt Entity',
                'country_code' => 'EG',
                'base_currency_code' => 'EGP',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $italy,
                'code' => 'TEST-IT',
                'legal_name' => 'Test Italy Entity',
                'country_code' => 'IT',
                'base_currency_code' => 'EUR',
                'timezone' => 'Europe/Rome',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        return [$egypt, $italy];
    }
}
