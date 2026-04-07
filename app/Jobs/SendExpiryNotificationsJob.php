<?php

namespace App\Jobs;

use App\Enums\ProposalStatus;
use App\Enums\QuotationStatus;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\ProposalExpiringNotification;
use App\Notifications\QuotationExpiring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendExpiryNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $daysBeforeExpiry = 2
    ) {}

    public function handle(): void
    {
        $this->notifyExpiringProposals();
        $this->notifyExpiringQuotations();
    }

    private function notifyExpiringProposals(): void
    {
        $expiringProposals = Proposal::where('status', ProposalStatus::SENT)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($this->daysBeforeExpiry)])
            ->with('lead')
            ->get();

        foreach ($expiringProposals as $proposal) {
            $daysUntilExpiry = now()->diffInDays($proposal->expiry_date, false);
            $daysUntilExpiry = max(0, $daysUntilExpiry);

            // Notify sales reps with sales role
            $salesUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'sales'))
                ->where('is_active', true)
                ->get();

            foreach ($salesUsers as $user) {
                $user->notify(new ProposalExpiringNotification($proposal, $daysUntilExpiry));
            }

            Log::info("Sent expiry notification for proposal #{$proposal->id}", [
                'days_until_expiry' => $daysUntilExpiry,
                'notified_users' => $salesUsers->count(),
            ]);
        }
    }

    private function notifyExpiringQuotations(): void
    {
        $expiringQuotations = Quotation::where('status', QuotationStatus::SENT)
            ->whereNotNull('valid_until')
            ->whereBetween('valid_until', [now(), now()->addDays($this->daysBeforeExpiry)])
            ->with('lead')
            ->get();

        foreach ($expiringQuotations as $quotation) {
            $daysUntilExpiry = now()->diffInDays($quotation->valid_until, false);
            $daysUntilExpiry = max(0, $daysUntilExpiry);

            // Notify sales reps
            $salesUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'sales'))
                ->where('is_active', true)
                ->get();

            foreach ($salesUsers as $user) {
                $user->notify(new QuotationExpiring($quotation, $daysUntilExpiry));
            }

            Log::info("Sent expiry notification for quotation #{$quotation->id}", [
                'days_until_expiry' => $daysUntilExpiry,
                'notified_users' => $salesUsers->count(),
            ]);
        }
    }
}
