<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDeliverable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SimplifiedModeRouteGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['simplification.enabled' => true]);

        foreach (['super-admin', 'admin', 'manager', 'developer', 'client'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }

    public function test_hidden_module_routes_are_blocked_for_simplified_internal_roles(): void
    {
        foreach (['admin', 'manager', 'developer', 'client'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/leads')
                ->assertForbidden()
                ->assertJsonPath('message', 'This module is disabled in the simplified ERP version.');
        }
    }

    public function test_hidden_module_routes_are_blocked_for_super_admin_too(): void
    {
        $this->actingAs($this->userWithRole('super-admin'), 'sanctum')
            ->getJson('/api/v1/user-points/leaderboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'This module is disabled in the simplified ERP version.');
    }

    public function test_client_billing_routes_are_blocked(): void
    {
        $clientUser = $this->userWithRole('client');
        $client = $this->ensureClientProfile($clientUser);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $invoice = Invoice::factory()->sent()->create(['project_id' => $project->id]);

        $this->actingAs($clientUser, 'sanctum')
            ->getJson('/api/v1/client/invoices')
            ->assertForbidden();

        $this->actingAs($clientUser, 'sanctum')
            ->postJson("/api/v1/client/invoices/{$invoice->id}/pay")
            ->assertForbidden();
    }

    public function test_sales_pricing_and_billing_route_groups_are_blocked(): void
    {
        $admin = $this->userWithRole('admin');
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $admin->id, 'role' => 'member']);

        foreach ([
            ['GET', '/api/v1/leads'],
            ['GET', '/api/v1/activities'],
            ['GET', '/api/v1/meetings'],
            ['GET', '/api/v1/proposals'],
            ['GET', '/api/v1/quotations'],
            ['POST', '/api/v1/quotations/calculate'],
            ['GET', '/api/v1/contracts'],
            ['GET', '/api/v1/invoices'],
            ['POST', "/api/v1/projects/{$project->id}/generate-milestone-invoice"],
            ['GET', '/api/v1/milestones/1'],
            ['GET', '/api/v1/sales-dashboard/summary'],
            ['GET', '/api/v1/analytics/sales/performance'],
            ['GET', '/api/v1/export/leads'],
        ] as [$method, $uri]) {
            $this->actingAs($admin, 'sanctum')
                ->json($method, $uri)
                ->assertForbidden();
        }
    }

    public function test_ui_approval_and_deliverable_workflow_routes_are_blocked(): void
    {
        $admin = $this->userWithRole('admin');
        $clientUser = $this->userWithRole('client');
        $client = $this->ensureClientProfile($clientUser);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $project->members()->create(['user_id' => $admin->id, 'role' => 'member']);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $deliverable = TaskDeliverable::factory()->create(['task_id' => $task->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/approve-ui")
            ->assertForbidden();

        $this->actingAs($clientUser, 'sanctum')
            ->postJson("/api/v1/client/projects/{$project->id}/approve-ui")
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/tasks/{$task->id}/deliverables")
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/deliverables/{$deliverable->id}/force-approve")
            ->assertForbidden();
    }

    public function test_points_performance_reviews_quality_and_innovation_routes_are_blocked(): void
    {
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('developer');
        $project = Project::factory()->create();
        $project->members()->create(['user_id' => $admin->id, 'role' => 'member']);

        foreach ([
            ['GET', '/api/v1/user-points'],
            ['GET', '/api/v1/user-points/leaderboard'],
            ['GET', "/api/v1/projects/{$project->id}/points-summary"],
            ['GET', '/api/v1/performance/evaluations'],
            ['GET', '/api/v1/admin/stats/user-performance'],
            ['GET', "/api/v1/admin/stats/employee-review/{$employee->id}"],
            ['GET', '/api/v1/admin/stats/quality'],
            ['GET', '/api/v1/code-reviews'],
            ['GET', '/api/v1/innovation-logs'],
        ] as [$method, $uri]) {
            $this->actingAs($admin, 'sanctum')
                ->json($method, $uri)
                ->assertForbidden();
        }
    }

    public function test_financial_internal_cost_reward_deduction_and_salary_routes_are_blocked(): void
    {
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('developer');

        foreach ([
            ['GET', '/api/v1/admin/stats/profitability'],
            ['PUT', "/api/v1/admin/users/{$employee->id}/internal-cost"],
            ['GET', '/api/v1/hr/deduction-types'],
            ['GET', '/api/v1/hr/employee-deductions'],
            ['GET', '/api/v1/hr/reward-types'],
            ['GET', '/api/v1/hr/employee-rewards'],
            ['GET', '/api/v1/hr/salary-adjustments'],
        ] as [$method, $uri]) {
            $this->actingAs($admin, 'sanctum')
                ->json($method, $uri)
                ->assertForbidden();
        }
    }

    public function test_operational_routes_are_not_blocked_by_simplified_mode(): void
    {
        $developer = $this->userWithRole('developer');

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');

        $this->actingAs($developer, 'sanctum')
            ->getJson('/api/v1/enums/task-categories')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        if ($role === 'client') {
            $this->ensureClientProfile($user);
        }

        return $user;
    }
}
