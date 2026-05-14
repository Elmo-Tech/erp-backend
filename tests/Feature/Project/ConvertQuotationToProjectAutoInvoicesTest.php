<?php

namespace Tests\Feature\Project;

use App\Enums\DocumentCreationType;
use App\Enums\QuotationStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Lead\Models\Quotation;
use App\Modules\Project\Actions\ConvertQuotationToProjectAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConvertQuotationToProjectAutoInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_finance_invoices_for_quotation_payment_stages(): void
    {
        Carbon::setTestNow('2026-05-13 10:00:00');

        $client = Client::factory()->create();
        $quotation = Quotation::create([
            'client_id' => $client->id,
            'status' => QuotationStatus::ACCEPTED,
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD,
            'currency' => 'USD',
            'final_total' => 1000,
            'payment_stages' => [
                ['name' => 'Kickoff', 'percentage' => 40, 'amount' => 400, 'due_date' => '2026-05-20'],
                ['stage_name' => 'Delivery', 'percentage' => 60, 'amount' => 600, 'due_date' => '2026-06-15'],
            ],
        ]);

        $project = app(ConvertQuotationToProjectAction::class)->execute($quotation->id);

        $kickoffInvoice = FinanceInvoice::where('project_id', $project->id)
            ->where('notes', 'Auto-created from quotation payment stage: Kickoff')
            ->firstOrFail();

        $this->assertSame($client->id, $kickoffInvoice->client_id);
        $this->assertSame($project->id, $kickoffInvoice->project_id);
        $this->assertSame('2026-05-13', $kickoffInvoice->issue_date->toDateString());
        $this->assertSame('2026-05-20', $kickoffInvoice->due_date->toDateString());
        $this->assertSame('400.00', $kickoffInvoice->subtotal);
        $this->assertSame('400.00', $kickoffInvoice->total_amount);
        $this->assertSame('draft', $kickoffInvoice->status);

        $deliveryInvoice = FinanceInvoice::where('project_id', $project->id)
            ->where('notes', 'Auto-created from quotation payment stage: Delivery')
            ->firstOrFail();

        $this->assertSame('2026-05-13', $deliveryInvoice->issue_date->toDateString());
        $this->assertSame('2026-06-15', $deliveryInvoice->due_date->toDateString());
        $this->assertSame('600.00', $deliveryInvoice->subtotal);
        $this->assertSame('600.00', $deliveryInvoice->total_amount);

        $this->assertSame(2, FinanceInvoice::where('project_id', $project->id)->count());

        Carbon::setTestNow();
    }

    public function test_missing_stage_due_date_defaults_to_today(): void
    {
        Carbon::setTestNow('2026-05-13 10:00:00');

        $client = Client::factory()->create();
        $quotation = Quotation::create([
            'client_id' => $client->id,
            'status' => QuotationStatus::ACCEPTED,
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD,
            'currency' => 'USD',
            'final_total' => 250,
            'payment_stages' => [
                ['name' => 'Undated stage', 'percentage' => 100, 'amount' => 250],
            ],
        ]);

        $project = app(ConvertQuotationToProjectAction::class)->execute($quotation->id);

        $invoice = FinanceInvoice::where('project_id', $project->id)->firstOrFail();

        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame('2026-05-13', $invoice->issue_date->toDateString());
        $this->assertSame('2026-05-13', $invoice->due_date->toDateString());
        $this->assertSame('250.00', $invoice->total_amount);

        Carbon::setTestNow();
    }
}
