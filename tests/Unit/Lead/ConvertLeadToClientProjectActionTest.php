<?php

namespace Tests\Unit\Lead;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Lead\Actions\ConvertLeadToClientProjectAction;
use App\Modules\Lead\Models\Lead;
use App\Modules\Meetings\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertLeadToClientProjectActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reparents_lead_polymorphic_records_to_the_new_client(): void
    {
        $lead = Lead::create([
            'title' => 'Acme Growth Platform',
            'description' => 'Pipeline discovery',
            'stage' => LeadStage::WON,
            'status' => LeadStatus::ACTIVE,
        ]);

        $meeting = Meeting::create([
            'model_type' => Lead::class,
            'model_id' => $lead->id,
            'title' => 'Discovery call',
            'status' => 'scheduled',
        ]);

        $invoice = Invoice::create([
            'model_type' => Lead::class,
            'model_id' => $lead->id,
            'invoice_number' => 'INV-2026-000001',
            'amount' => 1500,
            'currency' => 'USD',
            'status' => InvoiceStatus::DRAFT,
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        $result = app(ConvertLeadToClientProjectAction::class)->execute($lead);
        $client = $result['client'];

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'model_type' => $client::class,
            'model_id' => $client->id,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'model_type' => $client::class,
            'model_id' => $client->id,
        ]);
    }
}
