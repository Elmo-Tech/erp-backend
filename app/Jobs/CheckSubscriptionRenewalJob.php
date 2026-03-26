<?php

namespace App\Jobs;

use App\Enums\ContractStatus;
use App\Enums\LeadStatus;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\SubscriptionRenewalReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionRenewalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of days before expiration to check for (30, 7, or 1).
     * If null, checks all periods.
     */
    public ?int $daysBeforeExpiry;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $daysBeforeExpiry = null)
    {
        $this->daysBeforeExpiry = $daysBeforeExpiry;
    }

    /**
     * Execute the job.
     * Checks for contracts expiring in 30, 7, or 1 days and sends appropriate notifications.
     */
    public function handle(): void
    {
        try {
            // Define the reminder periods to check
            $reminderPeriods = $this->daysBeforeExpiry 
                ? [$this->daysBeforeExpiry] 
                : [30, 7, 1];

            foreach ($reminderPeriods as $days) {
                $this->processReminderPeriod($days);
            }

            // Also check for contracts expiring today and handle suspension
            $this->processExpiryDay();

            Log::info('CheckSubscriptionRenewalJob: Completed successfully');

        } catch (\Exception $e) {
            Log::error('CheckSubscriptionRenewalJob failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Process a specific reminder period (30, 7, or 1 days before expiry).
     */
    protected function processReminderPeriod(int $days): void
    {
        // Find active contracts expiring in exactly $days days
        $contracts = Contract::with(['quotation.lead.assignedTo', 'project'])
            ->where('status', ContractStatus::SIGNED)
            ->whereNotNull('end_date')
            ->whereDate('end_date', now()->addDays($days)->toDateString())
            ->get();

        Log::info("CheckSubscriptionRenewalJob: Found {$contracts->count()} contracts expiring in {$days} days");

        foreach ($contracts as $contract) {
            $this->sendReminderNotifications($contract, $days);
        }
    }

    /**
     * Process contracts that are expiring today.
     */
    protected function processExpiryDay(): void
    {
        // Find signed contracts that expired yesterday (to catch any missed ones)
        // and contracts expiring today
        $expiredContracts = Contract::with(['quotation.lead'])
            ->where('status', ContractStatus::SIGNED)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->subDays(1)->toDateString())
            ->get();

        Log::info("CheckSubscriptionRenewalJob: Found {$expiredContracts->count()} contracts expiring today or yesterday");

        foreach ($expiredContracts as $contract) {
            // Check if this is the first time we're processing this expiration
            $shouldProcessToday = $contract->end_date === now()->toDateString();
            
            if ($shouldProcessToday) {
                $this->sendExpiryNotification($contract);
                
                // Optionally: suspend the service
                // $this->suspendService($contract);
            }
        }
    }

    /**
     * Send reminder notifications to client and reference person.
     */
    protected function sendReminderNotifications(Contract $contract, int $days): void
    {
        // Determine reminder type string
        $reminderType = (string) $days;

        // Get client notification data
        $clientData = $this->getClientNotificationData($contract);
        
        if ($clientData) {
            try {
                $clientData['notifyable']->notify(
                    new SubscriptionRenewalReminderNotification(
                        $contract,
                        $days,
                        $reminderType
                    )
                );
                Log::info("Sent {$days}-day reminder to client for contract {$contract->contract_number}");
            } catch (\Exception $e) {
                Log::error("Failed to send {$days}-day reminder to client: " . $e->getMessage(), [
                    'contract_id' => $contract->id,
                ]);
            }
        }

        // Send notification to reference person (sales responsible)
        $referencePerson = $this->getReferencePerson($contract);
        
        if ($referencePerson) {
            try {
                $referencePerson->notify(
                    new SubscriptionRenewalReminderNotification(
                        $contract,
                        $days,
                        $reminderType . '_internal', // Different type for internal notification
                        $referencePerson
                    )
                );
                Log::info("Sent {$days}-day reminder to reference person for contract {$contract->contract_number}");
            } catch (\Exception $e) {
                Log::error("Failed to send {$days}-day reminder to reference person: " . $e->getMessage(), [
                    'contract_id' => $contract->id,
                    'reference_person_id' => $referencePerson->id,
                ]);
            }
        }
    }

    /**
     * Send notification for expiry day.
     */
    protected function sendExpiryNotification(Contract $contract): void
    {
        // Send to client
        $clientData = $this->getClientNotificationData($contract);
        
        if ($clientData) {
            try {
                $clientData['notifyable']->notify(
                    new SubscriptionRenewalReminderNotification(
                        $contract,
                        0,
                        'expired'
                    )
                );
                Log::info("Sent expiry notification to client for contract {$contract->contract_number}");
            } catch (\Exception $e) {
                Log::error("Failed to send expiry notification to client: " . $e->getMessage());
            }
        }

        // Send to reference person
        $referencePerson = $this->getReferencePerson($contract);
        
        if ($referencePerson) {
            try {
                $referencePerson->notify(
                    new SubscriptionRenewalReminderNotification(
                        $contract,
                        0,
                        'expired_internal',
                        $referencePerson
                    )
                );
                Log::info("Sent expiry notification to reference person for contract {$contract->contract_number}");
            } catch (\Exception $e) {
                Log::error("Failed to send expiry notification to reference person: " . $e->getMessage());
            }
        }
    }

    /**
     * Get client notification data from contract.
     * Looks for user/client associated with the contract.
     */
    protected function getClientNotificationData(Contract $contract): ?array
    {
        // Option 1: Get from quotation -> lead -> assigned user (as contact)
        if ($contract->quotation && $contract->quotation->lead) {
            $lead = $contract->quotation->lead;
            
            // If lead has been converted to client
            if ($lead->client_id && $lead->client) {
                $client = $lead->client;
                
                // Get client user account if exists
                if ($client->user_id) {
                    return [
                        'notifyable' => User::find($client->user_id),
                        'email' => $client->email,
                        'name' => $client->name,
                    ];
                }
                
                // Otherwise notify via client email
                return [
                    'notifyable' => null, // Will handle via ShouldQueue
                    'email' => $client->email,
                    'name' => $client->name,
                ];
            }
            
            // If not converted, use lead contact info
            return [
                'notifyable' => null,
                'email' => $lead->email,
                'name' => $lead->name,
            ];
        }

        // Option 2: Get from contract party_two data
        $partyTwo = $contract->party_two ?? [];
        
        if (!empty($partyTwo['email'])) {
            return [
                'notifyable' => null,
                'email' => $partyTwo['email'],
                'name' => $partyTwo['name'] ?? 'Valued Client',
            ];
        }

        Log::warning("Could not find client notification data for contract {$contract->id}");
        return null;
    }

    /**
     * Get reference person (sales responsible) for the contract.
     */
    protected function getReferencePerson(Contract $contract): ?User
    {
        // Option 1: Get from quotation -> lead -> assignedTo
        if ($contract->quotation && $contract->quotation->lead) {
            $lead = $contract->quotation->lead;
            
            if ($lead->assignedTo) {
                return $lead->assignedTo;
            }
        }

        // Option 2: Get from contract creator/owner
        if ($contract->createdBy) {
            return $contract->createdBy;
        }

        // Option 3: Get from project team
        if ($contract->project && $contract->project->team) {
            // Get first team member with sales role
            $salesLead = $contract->project->team->first(function ($member) {
                return $member->hasRole(['sales_manager', 'sales_rep', 'account_manager']);
            });
            
            if ($salesLead) {
                return $salesLead;
            }
        }

        // Fallback: Get any user with sales permissions
        return User::role(['sales_manager', 'sales_rep'])
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Handle contract expiration - suspend service (optional implementation).
     */
    protected function suspendService(Contract $contract): void
    {
        // Update contract status
        $contract->update([
            'status' => ContractStatus::SUSPENDED,
        ]);

        // Update related client status if applicable
        if ($contract->quotation && $contract->quotation->lead && $contract->quotation->lead->client) {
            $contract->quotation->lead->client->update([
                'status' => \App\Enums\ClientStatus::SUSPENDED,
            ]);
        }

        Log::info("Service suspended for contract {$contract->contract_number}");
    }
}
