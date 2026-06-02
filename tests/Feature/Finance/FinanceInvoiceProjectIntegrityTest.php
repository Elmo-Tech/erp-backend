<?php

namespace Tests\Feature\Finance;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceInvoiceProjectIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_a_project_that_belongs_to_a_different_client(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('manage_invoices');

        $selectedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $otherProject = Project::factory()->for($otherClient)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/invoices', [
                'clientId' => $selectedClient->id,
                'projectId' => $otherProject->id,
                'issueDate' => '2026-05-13',
                'dueDate' => '2026-05-20',
                'items' => [
                    ['description' => 'Implementation', 'quantity' => 1, 'unitPrice' => 100],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('projectId');
    }

    public function test_update_rejects_a_project_that_belongs_to_a_different_client(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('manage_invoices');

        $selectedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $currentProject = Project::factory()->for($selectedClient)->create();
        $otherProject = Project::factory()->for($otherClient)->create();

        $invoice = FinanceInvoice::create([
            'client_id' => $selectedClient->id,
            'project_id' => $currentProject->id,
            'invoice_number' => 'FINV-2026-000001',
            'issue_date' => '2026-05-13',
            'due_date' => '2026-05-20',
            'subtotal' => 100,
            'total_amount' => 100,
            'status' => 'draft',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/finance/invoices/{$invoice->id}", [
                'clientId' => $selectedClient->id,
                'projectId' => $otherProject->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('projectId');
    }

    public function test_subscription_invoice_creates_linked_subscription(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('manage_invoices');

        $client = Client::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/invoices', [
                'clientId' => $client->id,
                'invoiceType' => 'subscription',
                'billingCycle' => 'quarterly',
                'issueDate' => '2026-05-13',
                'dueDate' => '2026-05-20',
                'items' => [
                    ['description' => 'SaaS subscription', 'quantity' => 1, 'unitPrice' => 300],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.invoiceType', 'subscription')
            ->assertJsonPath('data.billingCycle', 'quarterly');

        $invoice = FinanceInvoice::findOrFail($response->json('data.id'));
        $subscription = Subscription::findOrFail($invoice->subscription_id);

        $this->assertSame($client->id, $subscription->client_id);
        $this->assertSame('quarterly', $subscription->billing_cycle);
        $this->assertSame('2026-05-20', $subscription->next_billing_date->toDateString());
        $this->assertSame('300.00', (string) $subscription->cost);
    }
}
