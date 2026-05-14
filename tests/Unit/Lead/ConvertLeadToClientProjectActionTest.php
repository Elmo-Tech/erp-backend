<?php

namespace Tests\Unit\Lead;

use App\Enums\ContractModel;
use App\Enums\ContractStatus;
use App\Enums\DocumentCreationType;
use App\Enums\InvoiceStatus;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Lead\Actions\ConvertLeadToClientProjectAction;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Quotation;
use App\Modules\Meetings\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_it_creates_finance_invoices_from_signed_standard_contract_payment_stages(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');

        $lead = Lead::create([
            'title' => 'Acme Growth Platform',
            'description' => 'Pipeline discovery',
            'stage' => LeadStage::WON,
            'status' => LeadStatus::ACTIVE,
        ]);

        Quotation::create([
            'lead_id' => $lead->id,
            'status' => QuotationStatus::ACCEPTED,
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD,
            'currency' => 'USD',
            'final_total' => 1000,
        ]);

        Contract::create([
            'lead_id' => $lead->id,
            'status' => ContractStatus::SIGNED,
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD,
            'contract_model' => ContractModel::STANDARD,
            'currency' => 'USD',
            'total_amount' => 1000,
            'payment_stages' => [
                ['stage_name' => 'Kickoff', 'percentage' => 40, 'amount' => 400, 'due_date' => '2026-05-20'],
                ['stage_name' => 'Launch', 'percentage' => 60, 'amount' => 600],
            ],
        ]);

        $result = app(ConvertLeadToClientProjectAction::class)->execute($lead);

        $kickoffInvoice = FinanceInvoice::where('project_id', $result['project']->id)
            ->where('notes', 'Auto-created from contract payment stage: Kickoff')
            ->firstOrFail();

        $this->assertSame($result['client']->id, $kickoffInvoice->client_id);
        $this->assertSame('2026-05-14', $kickoffInvoice->issue_date->toDateString());
        $this->assertSame('2026-05-20', $kickoffInvoice->due_date->toDateString());
        $this->assertSame('400.00', $kickoffInvoice->total_amount);

        $launchInvoice = FinanceInvoice::where('project_id', $result['project']->id)
            ->where('notes', 'Auto-created from contract payment stage: Launch')
            ->firstOrFail();

        $this->assertSame($result['client']->id, $launchInvoice->client_id);
        $this->assertSame('2026-05-14', $launchInvoice->due_date->toDateString());
        $this->assertSame('600.00', $launchInvoice->total_amount);
        $this->assertSame(2, FinanceInvoice::where('project_id', $result['project']->id)->count());

        Carbon::setTestNow();
    }

    public function test_it_creates_finance_subscription_from_signed_saas_contract(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');

        $lead = Lead::create([
            'title' => 'Acme SaaS Platform',
            'description' => 'Recurring platform subscription',
            'stage' => LeadStage::WON,
            'status' => LeadStatus::ACTIVE,
        ]);

        Contract::create([
            'lead_id' => $lead->id,
            'status' => ContractStatus::SIGNED,
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD,
            'contract_model' => ContractModel::SAAS_SUBSCRIPTION,
            'currency' => 'USD',
            'recurring_fee' => 250,
            'billing_cycle' => 'monthly',
            'subscription_start_date' => '2026-06-01',
        ]);

        $result = app(ConvertLeadToClientProjectAction::class)->execute($lead);

        $subscription = Subscription::where('client_id', $result['client']->id)->firstOrFail();

        $this->assertSame($result['project']->id, $subscription->project_id);
        $this->assertSame('Acme SaaS Platform', $subscription->name);
        $this->assertSame('monthly', $subscription->billing_cycle);
        $this->assertSame('250.00', $subscription->cost);
        $this->assertSame('USD', $subscription->currency);
        $this->assertSame('2026-06-01', $subscription->next_billing_date->toDateString());
        $this->assertSame('active', $subscription->status);
        $this->assertSame('Auto-created from SaaS contract during lead conversion.', $subscription->notes);

        Carbon::setTestNow();
    }

    public function test_it_creates_finance_invoices_from_accepted_quotation_when_no_signed_contract_exists(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');

        $lead = Lead::create([
            'title' => 'Acme Quoted Build',
            'stage' => LeadStage::WON,
            'status' => LeadStatus::ACTIVE,
        ]);

        Quotation::create([
            'lead_id' => $lead->id,
            'status' => QuotationStatus::ACCEPTED,
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD,
            'currency' => 'USD',
            'final_total' => 800,
            'payment_stages' => [
                ['stage_name' => 'Accepted quotation stage', 'percentage' => 50, 'amount' => 400],
            ],
        ]);

        Contract::create([
            'lead_id' => $lead->id,
            'status' => ContractStatus::DRAFT,
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD,
            'contract_model' => ContractModel::STANDARD,
            'currency' => 'USD',
            'total_amount' => 800,
            'payment_stages' => [
                ['stage_name' => 'Draft contract stage', 'percentage' => 100, 'amount' => 800],
            ],
        ]);

        $result = app(ConvertLeadToClientProjectAction::class)->execute($lead);

        $invoice = FinanceInvoice::where('project_id', $result['project']->id)->firstOrFail();

        $this->assertSame($result['client']->id, $invoice->client_id);
        $this->assertSame('Auto-created from quotation payment stage: Accepted quotation stage', $invoice->notes);
        $this->assertSame('400.00', $invoice->total_amount);
        $this->assertSame(1, FinanceInvoice::where('project_id', $result['project']->id)->count());

        Carbon::setTestNow();
    }
}
