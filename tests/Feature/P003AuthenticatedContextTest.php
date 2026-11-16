<?php

namespace Tests\Feature;

use App\Domain\Companies\AuthenticatedDatabaseContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class P003AuthenticatedContextTest extends TestCase
{
    // Unlike RefreshDatabase, this does NOT nest the service inside a test transaction.
    use DatabaseTruncation;

    private function seedCompanies(): array
    {
        DB::table('currencies')->insert([
            ['code' => 'EGP', 'name' => 'Synthetic EGP'],
            ['code' => 'EUR', 'name' => 'Synthetic EUR'],
        ]);
        $eg = (string) Str::uuid7();
        $it = (string) Str::uuid7();
        foreach ([[$eg, 'EG', 'EGP'], [$it, 'IT', 'EUR']] as [$id, $country, $currency]) {
            DB::table('companies')->insert([
                'id' => $id, 'code' => "P003B-{$country}", 'legal_name' => "Synthetic {$country}",
                'country_code' => $country, 'base_currency_code' => $currency,
                'timezone' => 'UTC', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return [$eg, $it];
    }

    private function membership(User $actor, string $companyId, string $role = 'viewer'): string
    {
        $id = (string) Str::uuid7();
        DB::table('company_memberships')->insert([
            'id' => $id, 'company_id' => $companyId, 'user_id' => $actor->id,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('company_role_assignments')->insert([
            'id' => (string) Str::uuid7(), 'company_id' => $companyId,
            'membership_id' => $id, 'role_key' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function contextSetting(): ?string
    {
        return DB::selectOne("SELECT nullif(current_setting('app.company_id', true), '') AS tenant")->tenant;
    }

    public function test_two_legal_companies_remain_isolated_across_reused_connection_and_forged_session(): void
    {
        [$eg, $it] = $this->seedCompanies();
        $actor = User::factory()->create();
        $this->membership($actor, $eg);
        $context = app(AuthenticatedDatabaseContext::class);

        $this->assertSame($eg, $context->run($actor, $eg, 'company.view',
            fn (string $id) => $this->contextSetting()));
        $this->assertNull($this->contextSetting());
        try {
            $context->run($actor, $it, 'company.view', fn () => $this->fail('Foreign work executed'));
            $this->fail('Missing Italian membership was accepted');
        } catch (AuthorizationException) {
            $this->assertNull($this->contextSetting());
        }

        $this->actingAs($actor, 'web')->withSession(['erp.company_id' => $eg]);
        $this->getJson('/api/v1/companies/authorized-read')->assertOk()
            ->assertJsonPath('company.id', $eg);
        $this->withSession(['erp.company_id' => $it]);
        $this->getJson('/api/v1/companies/authorized-read')->assertForbidden();
        $this->assertNull($this->contextSetting());

        // Same user and Laravel connection, switching after a separately validated grant.
        $this->membership($actor, $it);
        $this->assertSame($it, $context->run($actor, $it, 'company.view',
            fn () => $this->contextSetting()));
        $this->assertNull($this->contextSetting());
        $this->assertSame($eg, $context->run($actor, $eg, 'company.view',
            fn () => $this->contextSetting()));
        $this->assertNull($this->contextSetting());
    }

    public function test_session_and_job_principal_are_revalidated_after_revocation_and_deactivation(): void
    {
        [$eg] = $this->seedCompanies();
        $actor = User::factory()->create();
        $member = $this->membership($actor, $eg);
        $context = app(AuthenticatedDatabaseContext::class);
        $staleJobActor = User::findOrFail($actor->id);

        $this->assertSame($eg, $context->run($staleJobActor, $eg, 'company.view',
            fn () => $this->contextSetting()));
        DB::table('company_memberships')->where('id', $member)->update(['status' => 'revoked']);
        $this->expectException(AuthorizationException::class);
        $context->run($staleJobActor, $eg, 'company.view', fn () => $this->fail('Revoked actor ran'));
    }

    public function test_role_removal_in_same_session_blocks_next_transaction(): void
    {
        [$eg] = $this->seedCompanies();
        $actor = User::factory()->create();
        $member = $this->membership($actor, $eg);
        $context = app(AuthenticatedDatabaseContext::class);
        $this->assertSame($eg, $context->run($actor, $eg, 'company.view',
            fn () => $this->contextSetting()));
        DB::table('company_role_assignments')->where('membership_id', $member)->delete();
        try {
            $context->run($actor, $eg, 'company.view', fn () => $this->fail('Unprivileged work ran'));
            $this->fail('Missing role was accepted');
        } catch (AuthorizationException) {
            $this->assertNull($this->contextSetting());
        }
        DB::table('users')->where('id', $actor->id)->update(['is_active' => false]);
        $this->expectException(AuthorizationException::class);
        $context->run($actor, $eg, 'company.view', fn () => null);
    }

    public function test_rollback_clears_context_and_tampered_guc_cannot_authorize_another_company(): void
    {
        [$eg, $it] = $this->seedCompanies();
        $actor = User::factory()->create();
        $this->membership($actor, $eg);
        $context = app(AuthenticatedDatabaseContext::class);

        try {
            $context->run($actor, $eg, 'company.view', function () use ($it): void {
                DB::selectOne("SELECT set_config('app.company_id', ?, true)", [$it]);
                // GUC itself can be overwritten, so fail closed at the trust boundary.
            });
            $this->fail('Tampered context was silently committed');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('changed', $exception->getMessage());
        }
        $this->assertNull($this->contextSetting());

        // Even attacker-controlled session-level GUC must not grant another company.
        DB::selectOne("SELECT set_config('app.company_id', ?, false)", [$it]);
        try {
            $context->run($actor, $it, 'company.view', fn () => $this->fail('GUC bypassed membership'));
            $this->fail('GUC bypass accepted');
        } catch (AuthorizationException) {
            // Reset adversarial session setting in this disposable test.
            DB::selectOne("SELECT set_config('app.company_id', '', false)");
        }
        $this->assertNull($this->contextSetting());

        try {
            $context->run($actor, $eg, 'company.view', function (): void {
                throw new RuntimeException('synthetic write failure');
            });
            $this->fail('Exception swallowed');
        } catch (RuntimeException $exception) {
            $this->assertSame('synthetic write failure', $exception->getMessage());
        }
        $this->assertNull($this->contextSetting());
    }

    public function test_nested_or_unauthenticated_company_context_is_refused(): void
    {
        [$eg] = $this->seedCompanies();
        $actor = User::factory()->create();
        $this->membership($actor, $eg);
        $context = app(AuthenticatedDatabaseContext::class);
        try {
            DB::transaction(fn () => $context->run($actor, $eg, 'company.view', fn () => null));
            $this->fail('Nested transaction accepted');
        } catch (LogicException) {
            $this->assertNull($this->contextSetting());
        }
        try {
            $context->run($actor, 'not-a-uuid', 'company.view', fn () => null);
            $this->fail('Invalid company accepted');
        } catch (AuthorizationException) {
            $this->assertNull($this->contextSetting());
        }
    }
}
