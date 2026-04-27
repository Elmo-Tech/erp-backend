<?php

namespace App\Modules\Invoice\Actions;

use App\Modules\Invoice\Models\Invoice;
use App\Services\Reporting\PdfService;
use Barryvdh\DomPDF\PDF;

class GenerateInvoicePdfAction
{
    public function __construct(
        private PdfService $pdfService
    ) {}

    public function execute(Invoice $invoice): PDF
    {
        $invoice->loadMissing(['project.client', 'contract', 'createdBy']);

        return $this->pdfService->generatePdf(
            'invoice::invoices.pdf',
            [
                'invoice' => $invoice,
                'project' => $invoice->project,
                'client' => $invoice->project?->client,
                'contract' => $invoice->contract,
                'generatedAt' => now(),
                'locale' => app()->getLocale(),
            ],
            [
                'isRemoteEnabled' => false,
            ]
        );
    }

    public function filename(Invoice $invoice): string
    {
        return sprintf('%s.pdf', $invoice->invoice_number ?: 'invoice-'.$invoice->id);
    }
}
