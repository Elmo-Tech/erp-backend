<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Generate invoice for a specific milestone
     */
    public function generateForMilestone(Project $project, string $milestoneName): Invoice
    {
        return DB::transaction(function () use ($project, $milestoneName) {
            // Load project with contract
            $project->load('contract');
            
            if (!$project->contract) {
                throw new \InvalidArgumentException('Project must have a contract to generate milestone invoices');
            }

            $contract = $project->contract;

            // Check if invoice already exists for this milestone
            $existingInvoice = Invoice::where('project_id', $project->id)
                ->where('contract_id', $contract->id)
                ->where('milestone_name', $milestoneName)
                ->first();

            if ($existingInvoice) {
                throw new \InvalidArgumentException("Invoice already exists for milestone: {$milestoneName}");
            }

            // Get milestone details from contract payment plan
            $milestoneDetails = $this->getMilestoneDetails($milestoneName);
            
            if (!$milestoneDetails) {
                throw new \InvalidArgumentException("Unknown milestone: {$milestoneName}");
            }

            // Calculate invoice amount based on milestone percentage
            $amount = ($contract->total_amount * $milestoneDetails['percentage']) / 100;

            // Create invoice
            $invoice = Invoice::create([
                'project_id' => $project->id,
                'contract_id' => $contract->id,
                'milestone_name' => $milestoneName,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'amount' => $amount,
                'currency' => $contract->currency,
                'status' => InvoiceStatus::DRAFT,
                'due_date' => now()->addDays(30), // 30 days payment terms
                'notes' => "Invoice for milestone: {$milestoneName}",
                'created_by' => auth()->id(),
            ]);

            // Log the event
            $this->auditService->logInvoiceEvent(
                action: 'generated',
                invoice: $invoice,
                metadata: [
                    'milestone_name' => $milestoneName,
                    'milestone_percentage' => $milestoneDetails['percentage'],
                    'project_id' => $project->id,
                    'contract_id' => $contract->id,
                ],
                severity: 'info'
            );

            return $invoice;
        });
    }

    /**
     * Send invoice to client
     */
    public function sendInvoice(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            if ($invoice->status !== InvoiceStatus::DRAFT) {
                throw new \InvalidArgumentException('Only draft invoices can be sent');
            }

            $invoice->update([
                'status' => InvoiceStatus::SENT,
                'sent_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            // Log the event
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

            // TODO: Send email notification to client
            // This would integrate with your notification system

            return $invoice->fresh();
        });
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(Invoice $invoice, array $paymentDetails = []): Invoice
    {
        return DB::transaction(function () use ($invoice, $paymentDetails) {
            if (!$invoice->isPayable()) {
                throw new \InvalidArgumentException('Invoice cannot be marked as paid in current status');
            }

            $invoice->update([
                'status' => InvoiceStatus::PAID,
                'paid_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            // Log the payment event
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
        });
    }

    /**
     * Generate all pending milestone invoices for a project
     */
    public function generatePendingInvoices(Project $project): array
    {
        $project->load('contract');
        
        if (!$project->contract) {
            throw new \InvalidArgumentException('Project must have a contract to generate invoices');
        }

        $milestones = $this->getProjectMilestones();
        $generatedInvoices = [];

        foreach ($milestones as $milestone) {
            // Check if milestone should be invoiced based on project status
            if ($this->shouldGenerateInvoiceForMilestone($project, $milestone['name'])) {
                try {
                    $invoice = $this->generateForMilestone($project, $milestone['name']);
                    $generatedInvoices[] = $invoice;
                } catch (\InvalidArgumentException $e) {
                    // Invoice already exists, skip
                    continue;
                }
            }
        }

        return $generatedInvoices;
    }

    /**
     * Check if invoice should be generated for milestone based on project status
     */
    private function shouldGenerateInvoiceForMilestone(Project $project, string $milestoneName): bool
    {
        // Define milestone triggers based on project status
        $milestonesTriggers = [
            'Project Kickoff' => ['in_progress', 'ui_ux', 'development', 'testing', 'completed'],
            'UI/UX Completion' => ['development', 'testing', 'completed'],
            'Development Completion' => ['testing', 'completed'],
            'Final Delivery' => ['completed'],
        ];

        $triggers = $milestonesTriggers[$milestoneName] ?? [];
        
        return in_array($project->status->value, $triggers);
    }

    /**
     * Get milestone details
     */
    private function getMilestoneDetails(string $milestoneName): ?array
    {
        $milestones = $this->getProjectMilestones();
        
        return collect($milestones)->firstWhere('name', $milestoneName);
    }

    /**
     * Get standard project milestones
     * This matches the payment plan structure in ContractService
     */
    private function getProjectMilestones(): array
    {
        return [
            ['name' => 'Project Kickoff', 'percentage' => 30],
            ['name' => 'UI/UX Completion', 'percentage' => 30],
            ['name' => 'Development Completion', 'percentage' => 30],
            ['name' => 'Final Delivery', 'percentage' => 10],
        ];
    }

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices()
    {
        return Invoice::where('status', InvoiceStatus::SENT)
            ->where('due_date', '<', now())
            ->with(['project', 'contract'])
            ->get();
    }

    /**
     * Mark overdue invoices
     */
    public function markOverdueInvoices(): int
    {
        $overdueInvoices = $this->getOverdueInvoices();
        $count = 0;

        foreach ($overdueInvoices as $invoice) {
            $invoice->update(['status' => InvoiceStatus::OVERDUE]);
            
            $this->auditService->logInvoiceEvent(
                action: 'marked_overdue',
                invoice: $invoice,
                metadata: [
                    'days_overdue' => now()->diffInDays($invoice->due_date),
                    'amount' => $invoice->amount,
                ],
                severity: 'warning'
            );
            
            $count++;
        }

        return $count;
    }
}