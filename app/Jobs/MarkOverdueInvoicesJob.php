<?php

namespace App\Jobs;

use App\Modules\Invoice\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
     */
    public function handle(BillingService $billingService): void
    {
        Log::info('Starting MarkOverdueInvoicesJob');

        try {
            $count = $billingService->markOverdueInvoices();

            Log::info("MarkOverdueInvoicesJob completed successfully. Marked {$count} invoices as overdue.");
        } catch (\Exception $e) {
            Log::error("MarkOverdueInvoicesJob failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
