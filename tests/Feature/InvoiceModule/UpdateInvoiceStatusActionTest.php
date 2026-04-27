<?php

namespace Tests\Feature\InvoiceModule;

use App\Enums\InvoiceStatus;
use App\Modules\User\Models\User;
use App\Modules\Invoice\Actions\UpdateInvoiceStatusAction;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateInvoiceStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_endpoint_action_can_mark_draft_invoice_as_paid(): void
    {
        $actor = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::DRAFT,
            'paid_at' => null,
        ]);

        $updated = app(UpdateInvoiceStatusAction::class)->markAsPaid($invoice, [], $actor->id);

        $this->assertSame(InvoiceStatus::PAID, $updated->status);
        $this->assertNotNull($updated->paid_at);
        $this->assertSame($actor->id, $updated->updated_by);
    }
}
