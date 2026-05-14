<?php

namespace Tests\Feature\Finance;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Resources\FinanceInvoiceResource;
use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FinanceInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_always_returns_client_and_project_objects(): void
    {
        $client = Client::factory()->create([
            'display_name' => 'Acme Operations',
            'company_name' => 'Acme LLC',
        ]);
        $project = Project::factory()->for($client)->create(['title' => 'Automation Build']);

        $invoice = FinanceInvoice::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'invoice_number' => 'FINV-2026-000001',
            'issue_date' => '2026-05-13',
            'due_date' => '2026-05-20',
            'subtotal' => 1000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1000,
            'status' => 'draft',
        ]);

        $payload = (new FinanceInvoiceResource($invoice))->toArray(Request::create('/'));

        $this->assertSame('Acme Operations', $payload['client']['displayName']);
        $this->assertSame('Acme LLC', $payload['client']['companyName']);
        $this->assertSame('Automation Build', $payload['project']['title']);
    }
}
