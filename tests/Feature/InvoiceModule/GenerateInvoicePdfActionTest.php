<?php

namespace Tests\Feature\InvoiceModule;

use App\Modules\Invoice\Actions\GenerateInvoicePdfAction;
use App\Modules\Client\Models\Client;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateInvoicePdfActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_pdf_for_an_invoice_from_an_isolated_action(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertInstanceOf(Project::class, $invoice->project);
        $this->assertInstanceOf(Client::class, $invoice->client);

        $pdf = app(GenerateInvoicePdfAction::class)->execute($invoice);

        $this->assertStringStartsWith('%PDF', $pdf->output());
    }
}
