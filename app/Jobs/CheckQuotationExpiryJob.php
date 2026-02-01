<?php

namespace App\Jobs;

use App\Models\Quotation;
use App\Models\User;
use App\Notifications\QuotationExpiring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckQuotationExpiryJob implements ShouldQueue
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
     * Finds quotations expiring in 3 days and sends notifications to sales reps.
     */
    public function handle(): void
    {
        try {
            // Find quotations expiring in exactly 3 days
            $expiringQuotations = Quotation::with(['lead.assignedTo'])
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', now()->addDays(3)->toDateString())
                ->whereIn('status', ['draft', 'sent', 'under_review']) // Only active quotations
                ->get();

            Log::info('CheckQuotationExpiryJob: Found ' . $expiringQuotations->count() . ' quotations expiring in 3 days');

            foreach ($expiringQuotations as $quotation) {
                $this->sendExpiryNotification($quotation);
            }

            // Also check for already expired quotations that need status update
            $expiredQuotations = Quotation::whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->whereIn('status', ['draft', 'sent', 'under_review'])
                ->get();

            foreach ($expiredQuotations as $quotation) {
                $quotation->update(['status' => 'expired']);
                Log::info("Quotation {$quotation->id} marked as expired");
            }

        } catch (\Exception $e) {
            Log::error('CheckQuotationExpiryJob failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Send expiry notification to the assigned sales rep
     */
    private function sendExpiryNotification(Quotation $quotation): void
    {
        try {
            // Get the assigned sales rep from the lead
            $assignedUser = $quotation->lead->assignedTo ?? null;
            
            if (!$assignedUser) {
                // Fallback: notify all users with quotation management permissions
                $salesUsers = User::permission('manage_quotations')->get();
                
                foreach ($salesUsers as $user) {
                    $user->notify(new QuotationExpiring($quotation));
                }
                
                Log::info("Quotation {$quotation->id} expiry notification sent to all sales users (no assigned rep)");
            } else {
                // Notify the assigned sales rep
                $assignedUser->notify(new QuotationExpiring($quotation));
                
                Log::info("Quotation {$quotation->id} expiry notification sent to user {$assignedUser->id}");
            }

        } catch (\Exception $e) {
            Log::error("Failed to send expiry notification for quotation {$quotation->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CheckQuotationExpiryJob failed permanently', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}