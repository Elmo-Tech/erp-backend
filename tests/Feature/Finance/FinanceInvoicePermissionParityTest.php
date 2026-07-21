<?php

namespace Tests\Feature\Finance;

use App\Enums\ProjectRole;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinanceInvoicePermissionParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_permission_lists_only_invoices_in_the_users_project_scope(): void
    {
        $viewer = $this->userWithPermissions(['view_invoices']);
        $visibleProject = Project::factory()->create();
        $foreignProject = Project::factory()->create();
        $this->addProjectMember($visibleProject, $viewer);
        $visibleInvoice = $this->invoice($visibleProject);
        $foreignInvoice = $this->invoice($foreignProject);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/finance/invoices')
            ->assertOk();

        $invoiceIds = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertContains($visibleInvoice->id, $invoiceIds);
        $this->assertNotContains($foreignInvoice->id, $invoiceIds);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/finance/invoices/{$foreignInvoice->id}")
            ->assertForbidden();
    }

    public function test_create_permission_requires_target_object_scope(): void
    {
        $creator = $this->userWithPermissions(['create_invoices']);
        $visibleProject = Project::factory()->create();
        $foreignProject = Project::factory()->create();
        $this->addProjectMember($visibleProject, $creator);

        $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/finance/invoices', $this->invoicePayload($visibleProject))
            ->assertCreated();

        $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/finance/invoices', $this->invoicePayload($foreignProject))
            ->assertForbidden();
    }

    public function test_edit_delete_and_send_permissions_are_independent(): void
    {
        $editor = $this->userWithPermissions(['edit_invoices']);
        $project = Project::factory()->create();
        $this->addProjectMember($project, $editor);
        $invoice = $this->invoice($project);

        $this->actingAs($editor, 'sanctum')
            ->patchJson("/api/v1/finance/invoices/{$invoice->id}", ['notes' => 'Updated'])
            ->assertOk();

        $this->actingAs($editor, 'sanctum')
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/send")
            ->assertForbidden();

        $this->actingAs($editor, 'sanctum')
            ->deleteJson("/api/v1/finance/invoices/{$invoice->id}")
            ->assertForbidden();

        $sender = $this->userWithPermissions(['send_invoices']);
        $this->addProjectMember($project, $sender);

        $this->actingAs($sender, 'sanctum')
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->actingAs($sender, 'sanctum')
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $deleter = $this->userWithPermissions(['delete_invoices']);
        $this->addProjectMember($project, $deleter);

        $this->actingAs($deleter, 'sanctum')
            ->deleteJson("/api/v1/finance/invoices/{$invoice->id}")
            ->assertOk();

        $this->assertSoftDeleted('finance_invoices', ['id' => $invoice->id]);
    }

    public function test_manage_invoices_is_an_umbrella_but_not_an_object_scope_bypass(): void
    {
        $manager = $this->userWithPermissions(['manage_invoices']);
        $visibleProject = Project::factory()->create();
        $foreignProject = Project::factory()->create();
        $this->addProjectMember($visibleProject, $manager);
        $visibleInvoice = $this->invoice($visibleProject);
        $foreignInvoice = $this->invoice($foreignProject);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/finance/invoices/{$visibleInvoice->id}/send")
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/finance/invoices/{$visibleInvoice->id}")
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/finance/invoices/{$foreignInvoice->id}")
            ->assertForbidden();
    }

    public function test_send_invoice_is_lifecycle_guarded_for_paid_invoices(): void
    {
        $sender = $this->userWithPermissions(['send_invoices']);
        $project = Project::factory()->create();
        $this->addProjectMember($project, $sender);
        $invoice = $this->invoice($project, ['status' => 'paid']);

        $this->actingAs($sender, 'sanctum')
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/send")
            ->assertUnprocessable();

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    private function invoice(Project $project, array $attributes = []): FinanceInvoice
    {
        return FinanceInvoice::create(array_merge([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'invoice_number' => 'FINV-2026-'.str_pad((string) (FinanceInvoice::withTrashed()->count() + 1), 6, '0', STR_PAD_LEFT),
            'issue_date' => '2026-07-20',
            'due_date' => '2026-07-30',
            'subtotal' => 100,
            'total_amount' => 100,
            'status' => 'draft',
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Project $project): array
    {
        return [
            'clientId' => $project->client_id,
            'projectId' => $project->id,
            'issueDate' => '2026-07-20',
            'dueDate' => '2026-07-30',
            'items' => [
                ['description' => 'Implementation', 'quantity' => 1, 'unitPrice' => 100],
            ],
        ];
    }

    private function addProjectMember(Project $project, User $user): void
    {
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MANAGER,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
