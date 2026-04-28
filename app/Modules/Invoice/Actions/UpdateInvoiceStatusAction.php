<?php

namespace App\Modules\Invoice\Actions;

use App\Enums\InvoiceStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateInvoiceStatusAction
{
    public function __construct(
        private AuditService $auditService
    ) {}

    public function send(Invoice $invoice, ?int $actorId): Invoice
    {
        return $this->execute($invoice, InvoiceStatus::SENT, $actorId);
    }

    public function markAsPaid(Invoice $invoice, array $paymentDetails, ?int $actorId): Invoice
    {
        return $this->execute($invoice, InvoiceStatus::PAID, $actorId, $paymentDetails);
    }

    public function execute(Invoice $invoice, InvoiceStatus $status, ?int $actorId, array $metadata = []): Invoice
    {
        return DB::transaction(function () use ($invoice, $status, $actorId, $metadata) {
            return match ($status) {
                InvoiceStatus::SENT => $this->markSent($invoice, $actorId),
                InvoiceStatus::PAID => $this->markPaid($invoice, $actorId, $metadata),
                default => throw new InvalidArgumentException("Unsupported invoice status transition to {$status->value}"),
            };
        });
    }

    private function markSent(Invoice $invoice, ?int $actorId): Invoice
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new InvalidArgumentException('Only draft invoices can be sent');
        }

        $invoice->update([
            'status' => InvoiceStatus::SENT,
            'sent_at' => now(),
            'updated_by' => $actorId,
        ]);

        $this->auditService->logInvoiceEvent(
            action: 'sent',
            invoice: $invoice,
            metadata: [
                'sent_to' => $invoice->project->client->email ?? 'Unknown',
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
            ],
            severity: 'info'
        );

        return $invoice->fresh();
    }

    private function markPaid(Invoice $invoice, ?int $actorId, array $paymentDetails): Invoice
    {
        if (! $this->canBeMarkedPaid($invoice)) {
            throw new InvalidArgumentException('Invoice cannot be marked as paid in current status');
        }

        $invoice->update([
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'updated_by' => $actorId,
        ]);

        $this->auditService->logPaymentEvent(
            action: 'received',
            invoice: $invoice,
            metadata: array_merge($paymentDetails, [
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
                'milestone_name' => $invoice->milestone_name,
            ]),
            severity: 'info'
        );

        return $invoice->fresh();
    }

    private function canBeMarkedPaid(Invoice $invoice): bool
    {
        return in_array($invoice->status, [
            InvoiceStatus::DRAFT,
            InvoiceStatus::SENT,
            InvoiceStatus::OVERDUE,
        ], true);
    }
}
