<?php

namespace App\Jobs;

use App\Services\Client\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to mark overdue invoices.
 *
 * This job runs daily at midnight to check for invoices that have passed
 * their due date and are still in 'sent' status, marking them as 'overdue'.
 * This helps track late payments and provides visibility into outstanding
 * invoices that require follow-up.
 *
 * Requirements: 18.1, 18.2, 18.3, 18.4
 */
class MarkOverdueInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * This method:
     * 1. Calls InvoiceService::markOverdueInvoices() to update invoice statuses
     * 2. Logs the number of invoices that were marked as overdue
     * 3. Provides audit trail for the scheduled operation
     *
     * @param InvoiceService $invoiceService
     * @return void
     */
    public function handle(InvoiceService $invoiceService): void
    {
        $count = $invoiceService->markOverdueInvoices();

        Log::info("Marked {$count} invoices as overdue.", [
            'job' => self::class,
            'count' => $count,
            'executed_at' => now()->toISOString(),
        ]);
    }
}