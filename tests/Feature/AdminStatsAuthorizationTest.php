<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reporting\EmployeeReviewService;
use App\Services\Reporting\PdfService;
use App\Services\Reporting\ProfitabilityService;
use App\Services\Reporting\QualityService;
use App\Services\Reporting\UserPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminStatsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        config(['simplification.enabled' => false]);
        Permission::findOrCreate('view_admin_stats', 'web');
        Permission::findOrCreate('view_internal_costs', 'web');
        $this->employee = User::factory()->create();

        // Exercise the real route authorization without generating PDFs or report calculations.
        $this->mock(ProfitabilityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getProfitabilityReport')->andReturn(new LengthAwarePaginator([], 0, 15));
        });
        $this->mock(QualityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getQualityOverview')->andReturn([]);
            $mock->shouldReceive('getBounceRateByDeveloper')->andReturn(collect());
            $mock->shouldReceive('getTopBouncingTasks')->andReturn(collect());
        });
        $this->mock(UserPerformanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAllUserPerformance')->andReturn(collect());
            $mock->shouldReceive('getAttendanceAnalytics')->andReturn([]);
        });
        $this->mock(EmployeeReviewService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getEmployeeReview')->andReturn(['projects' => [], 'points' => []]);
        });
        $this->mock(PdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('downloadPdf')->andReturn(response('PDF export', 200, ['Content-Type' => 'application/pdf']));
        });
    }

    public function test_super_admin_can_access_reports_without_explicit_permissions_using_a_bearer_token(): void
    {
        $user = $this->userWithRole('super-admin');
        $this->assertFalse($user->can('view_admin_stats'));
        $this->assertFalse($user->can('view_internal_costs'));
        $this->assertCount(0, $user->getAllPermissions());

        $token = $user->createToken('reports-test')->plainTextToken;

        foreach ($this->reportPaths() as $path) {
            $this->withToken($token)->getJson($path)->assertOk();
        }
    }

    public function test_admin_with_explicit_permissions_can_still_access_reports(): void
    {
        $user = $this->userWithRole('admin');
        $user->givePermissionTo(['view_admin_stats', 'view_internal_costs']);

        foreach ($this->reportPaths() as $path) {
            $this->actingAs($user, 'sanctum')->getJson($path)->assertOk();
        }
    }

    public function test_other_roles_without_report_permissions_are_denied(): void
    {
        foreach (['admin', 'manager', 'developer', 'client'] as $role) {
            $user = $this->userWithRole($role);

            foreach ($this->reportPaths() as $path) {
                $this->actingAs($user, 'sanctum')->getJson($path)->assertForbidden();
            }
        }
    }

    public function test_a_super_admin_display_name_does_not_grant_report_access(): void
    {
        $user = User::factory()->create(['name' => 'The Tables (Super Admin)']);

        foreach ($this->reportPaths() as $path) {
            $this->actingAs($user, 'sanctum')->getJson($path)->assertForbidden();
        }
    }

    public function test_profitability_still_requires_both_permissions_for_non_super_admins(): void
    {
        foreach (['view_admin_stats', 'view_internal_costs'] as $permission) {
            $user = $this->userWithRole('admin');
            $user->givePermissionTo($permission);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/admin/stats/profitability')
                ->assertForbidden();
        }
    }

    public function test_report_endpoints_require_authentication(): void
    {
        foreach ($this->reportPaths() as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
    }

    public function test_super_admin_access_respects_simplified_module_guards(): void
    {
        config(['simplification.enabled' => true]);
        $user = $this->userWithRole('super-admin');

        foreach ($this->reportPaths() as $path) {
            $response = $this->actingAs($user, 'sanctum')->getJson($path);

            if (in_array($path, ['/api/v1/admin/stats/overview', '/api/v1/admin/stats/attendance'], true)) {
                $response->assertOk();
            } else {
                $response->assertForbidden()
                    ->assertJsonPath('message', 'This module is disabled in the simplified ERP version.');
            }
        }
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($roleName, 'web'));

        return $user;
    }

    private function reportPaths(): array
    {
        return [
            '/api/v1/admin/stats/overview',
            '/api/v1/admin/stats/attendance',
            '/api/v1/admin/stats/quality',
            '/api/v1/admin/stats/user-performance',
            '/api/v1/admin/stats/profitability',
            "/api/v1/admin/stats/employee-review/{$this->employee->id}",
            "/api/v1/admin/stats/employee-review/{$this->employee->id}/export",
        ];
    }
}
