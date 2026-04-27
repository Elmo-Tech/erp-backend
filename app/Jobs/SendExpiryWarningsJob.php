<?php

namespace App\Jobs;

use App\Enums\ProposalStatus;
use App\Enums\QuotationStatus;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Modules\User\Models\User;
use App\Notifications\ExpiryWarningNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendExpiryWarningsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $warningDays = 2
    ) {}

    /**
     * Execute the job.
     * Sends warnings for proposals and quotations expiring soon
     */
    public function handle(): void
    {
        $this->sendProposalWarnings();
        $this->sendQuotationWarnings();
    }

    private function sendProposalWarnings(): void
    {
        $proposals = Proposal::with('lead')
            ->where('status', ProposalStatus::SENT)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($this->warningDays)])
            ->get();

        foreach ($proposals as $proposal) {
            $daysRemaining = now()->diffInDays($proposal->expiry_date);
            $user = User::find($proposal->created_by);

            if ($user) {
                try {
                    $user->notify(new ExpiryWarningNotification($proposal, 'proposal', $daysRemaining));

                    Log::info('Proposal expiry warning sent', [
                        'proposal_id' => $proposal->id,
                        'user_id' => $user->id,
                        'days_remaining' => $daysRemaining,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send proposal expiry warning', [
                        'proposal_id' => $proposal->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function sendQuotationWarnings(): void
    {
        $quotations = Quotation::with('lead')
            ->where('status', QuotationStatus::SENT)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($this->warningDays)])
            ->get();

        foreach ($quotations as $quotation) {
            $daysRemaining = now()->diffInDays($quotation->expiry_date);
            $user = User::find($quotation->created_by);

            if ($user) {
                try {
                    $user->notify(new ExpiryWarningNotification($quotation, 'quotation', $daysRemaining));

                    Log::info('Quotation expiry warning sent', [
                        'quotation_id' => $quotation->id,
                        'user_id' => $user->id,
                        'days_remaining' => $daysRemaining,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send quotation expiry warning', [
                        'quotation_id' => $quotation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
