<?php

namespace Tests\Feature\Reports;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_require_view_reports_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports/overview')
            ->assertForbidden();
    }

    public function test_user_with_view_reports_can_load_all_report_endpoints(): void
    {
        $user = $this->reportsUser(['view_reports']);

        foreach (['overview', 'projects', 'team-time', 'sales-finance'] as $report) {
            $this->actingAs($user, 'sanctum')
                ->getJson("/api/v1/reports/{$report}")
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['data']);
        }
    }

    public function test_sensitive_cost_and_profitability_fields_are_hidden_without_permissions(): void
    {
        $user = $this->reportsUser(['view_reports']);
        $client = Client::factory()->create();

        Project::factory()->create([
            'client_id' => $client->id,
            'title' => 'Internal Cost Project',
            'budget' => 5000,
            'status' => ProjectStatus::ACTIVE,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports/projects');

        $response->assertOk()
            ->assertJsonPath('data.rows.0.internalCost', null)
            ->assertJsonPath('data.rows.0.profit', null)
            ->assertJsonPath('data.rows.0.marginPercent', null);
    }

    public function test_date_and_project_filters_scope_report_data(): void
    {
        $user = $this->reportsUser(['view_reports', 'view_profitability', 'view_cost']);
        $client = Client::factory()->create();
        $includedProject = Project::factory()->create([
            'client_id' => $client->id,
            'title' => 'Included Project',
            'budget' => 10000,
            'status' => ProjectStatus::ACTIVE,
        ]);
        $excludedProject = Project::factory()->create([
            'client_id' => $client->id,
            'title' => 'Excluded Project',
            'budget' => 20000,
            'status' => ProjectStatus::COMPLETED,
        ]);

        FinanceInvoice::create([
            'client_id' => $client->id,
            'project_id' => $includedProject->id,
            'invoice_number' => 'FINV-2026-000001',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'subtotal' => 1000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1000,
            'status' => 'paid',
        ]);

        FinanceInvoice::create([
            'client_id' => $client->id,
            'project_id' => $excludedProject->id,
            'invoice_number' => 'FINV-2026-000002',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-10',
            'subtotal' => 3000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 3000,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/reports/projects?from=2026-06-01&to=2026-06-30&projectId={$includedProject->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.projectId', $includedProject->id)
            ->assertJsonPath('data.rows.0.invoicedTotal', 1000);
    }

    public function test_csv_export_uses_selected_report_filters(): void
    {
        $user = $this->reportsUser(['view_reports']);
        $client = Client::factory()->create(['display_name' => 'CSV Client']);
        $project = Project::factory()->create([
            'client_id' => $client->id,
            'title' => 'CSV Project',
            'status' => ProjectStatus::ACTIVE,
        ]);

        Project::factory()->create([
            'client_id' => $client->id,
            'title' => 'Filtered Out Project',
            'status' => ProjectStatus::COMPLETED,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reports/export?report=projects&status=active');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('CSV Project', $csv);
        $this->assertStringNotContainsString('Filtered Out Project', $csv);
        $this->assertStringContainsString((string) $project->id, $csv);
    }

    public function test_team_time_report_filters_by_user(): void
    {
        $manager = $this->reportsUser(['view_reports', 'view_user_performance']);
        $includedUser = User::factory()->create(['name' => 'Included Worker']);
        $excludedUser = User::factory()->create(['name' => 'Excluded Worker']);
        $project = Project::factory()->create();
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Timed Task',
            'status' => ExecutionTaskStatus::IN_PROGRESS,
            'assignee_id' => $includedUser->id,
        ]);

        ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $includedUser->id,
            'start_time' => '2026-06-01 09:00:00',
            'end_time' => '2026-06-01 10:00:00',
            'duration_minutes' => 60,
        ]);

        ProjectTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $excludedUser->id,
            'start_time' => '2026-06-01 09:00:00',
            'end_time' => '2026-06-01 11:00:00',
            'duration_minutes' => 120,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/reports/team-time?userId={$includedUser->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.userId', $includedUser->id)
            ->assertJsonPath('data.rows.0.loggedMinutes', 60);
    }

    private function reportsUser(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
