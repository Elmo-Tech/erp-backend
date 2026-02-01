<?php

namespace App\Services\Sales;

use App\Enums\ContractStatus;
use App\Enums\ProjectStatus;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Quotation;
use App\Services\Projects\ProjectConversionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContractService
{
    public function __construct(
        private ProjectConversionService $projectConversionService
    ) {}

    /**
     * Generate contract draft from quotation
     */
    public function generateDraft(Quotation $quotation): Contract
    {
        return DB::transaction(function () use ($quotation) {
            // Load quotation with lead relationship
            $quotation->load('lead');

            // Generate contract content from template
            $content = $this->generateContractContent($quotation);
            
            // Prepare party information
            $partyOne = [
                'company' => config('app.company_name', 'Your Company'),
                'representative' => config('app.company_representative', 'Company Representative'),
                'title' => config('app.company_title', 'CEO'),
                'address' => config('app.company_address', 'Company Address'),
            ];
            
            $partyTwo = [
                'name' => $quotation->lead->name,
                'email' => $quotation->lead->email,
                'company' => $quotation->lead->company,
                'phone' => $quotation->lead->phone ?? '',
            ];

            // Create contract
            $contract = Contract::create([
                'contract_number' => Contract::generateContractNumber(),
                'quotation_id' => $quotation->id,
                'title' => "Service Agreement - {$quotation->lead->name}",
                'content' => $content,
                'party_one' => $partyOne,
                'party_two' => $partyTwo,
                'total_amount' => $quotation->final_price,
                'currency' => $quotation->currency,
                'payment_terms' => 'Payment due within 30 days of invoice date',
                'start_date' => now()->toDateString(),
                'status' => ContractStatus::DRAFT,
                'created_by' => auth()->id(),
            ]);

            return $contract;
        });
    }

    /**
     * Mark contract as signed and activate project
     */
    public function markAsSigned(Contract $contract, UploadedFile $signedPdf): Contract
    {
        return DB::transaction(function () use ($contract, $signedPdf) {
            // Validate contract can be signed
            if (!$contract->canBeSigned()) {
                throw new \InvalidArgumentException('Contract cannot be signed in current status');
            }

            // Store signed PDF (we'll store the path in a custom field or handle differently)
            $path = $signedPdf->store('contracts/signed', 'private');

            // Update contract
            $contract->update([
                'status' => ContractStatus::SIGNED,
                'signed_at' => now(),
                'signed_by_client' => $contract->party_two['name'] ?? 'Client',
                'updated_by' => auth()->id(),
            ]);

            // Store the PDF path in a custom way since the existing table doesn't have signed_pdf_path
            // You could add this field to the existing table or store it differently
            
            // Activate or create project
            $this->activateProject($contract);

            return $contract->fresh();
        });
    }

    /**
     * Update contract status
     */
    public function updateStatus(Contract $contract, ContractStatus $status): Contract
    {
        $contract->update([
            'status' => $status,
            'updated_by' => auth()->id(),
        ]);

        return $contract->fresh();
    }

    /**
     * Generate contract content from template
     */
    private function generateContractContent(Quotation $quotation): string
    {
        $template = $this->getContractTemplate();
        
        // Prepare payment plan table
        $paymentPlanTable = $this->generatePaymentPlanTable($quotation);
        
        // Prepare estimations breakdown
        $estimationsBreakdown = $this->generateEstimationsBreakdown($quotation);

        // Replace placeholders
        $replacements = [
            '{{client_name}}' => $quotation->lead->name ?? 'Client',
            '{{client_email}}' => $quotation->lead->email ?? '',
            '{{client_company}}' => $quotation->lead->company ?? '',
            '{{total_amount}}' => number_format($quotation->final_price, 2),
            '{{currency}}' => $quotation->currency,
            '{{payment_plan}}' => $paymentPlanTable,
            '{{estimations_breakdown}}' => $estimationsBreakdown,
            '{{contract_date}}' => now()->format('F j, Y'),
            '{{hourly_rate}}' => number_format($quotation->hourly_rate, 2),
            '{{discount_percentage}}' => number_format($quotation->discount_percentage, 2),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get contract template
     */
    private function getContractTemplate(): string
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <title>Service Agreement</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .table th, .table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .signature { margin-top: 50px; }
        .signature-line { border-bottom: 1px solid #000; width: 200px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SERVICE AGREEMENT</h1>
        <p>Date: {{contract_date}}</p>
    </div>

    <div class="section">
        <h2>PARTIES</h2>
        <p><strong>Service Provider:</strong> [Company Name]</p>
        <p><strong>Client:</strong> {{client_name}}</p>
        <p><strong>Company:</strong> {{client_company}}</p>
        <p><strong>Email:</strong> {{client_email}}</p>
    </div>

    <div class="section">
        <h2>PROJECT SCOPE & PRICING</h2>
        <p>The following services will be provided at an hourly rate of {{currency}} {{hourly_rate}}:</p>
        {{estimations_breakdown}}
        
        <p><strong>Total Project Cost:</strong> {{currency}} {{total_amount}}</p>
        <p><strong>Discount Applied:</strong> {{discount_percentage}}%</p>
    </div>

    <div class="section">
        <h2>PAYMENT TERMS</h2>
        {{payment_plan}}
    </div>

    <div class="section">
        <h2>TERMS & CONDITIONS</h2>
        <ul>
            <li>Payment is due within 30 days of invoice date</li>
            <li>Late payments may incur additional charges</li>
            <li>All work will be delivered according to agreed timeline</li>
            <li>Client approval is required for UI/UX deliverables</li>
            <li>Source code will be delivered upon final payment</li>
        </ul>
    </div>

    <div class="signature">
        <div style="display: flex; justify-content: space-between;">
            <div>
                <p><strong>Service Provider</strong></p>
                <div class="signature-line"></div>
                <p>Date: _____________</p>
            </div>
            <div>
                <p><strong>Client: {{client_name}}</strong></p>
                <div class="signature-line"></div>
                <p>Date: _____________</p>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Generate payment plan table HTML
     */
    private function generatePaymentPlanTable(Quotation $quotation): string
    {
        // For now, create a simple payment plan
        // This can be enhanced later to use actual payment plan data from quotation
        $milestones = [
            ['name' => 'Project Kickoff', 'percentage' => 30],
            ['name' => 'UI/UX Completion', 'percentage' => 30],
            ['name' => 'Development Completion', 'percentage' => 30],
            ['name' => 'Final Delivery', 'percentage' => 10],
        ];

        $html = '<table class="table">';
        $html .= '<thead><tr><th>Milestone</th><th>Percentage</th><th>Amount</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($milestones as $milestone) {
            $amount = ($quotation->final_price * $milestone['percentage']) / 100;
            $html .= sprintf(
                '<tr><td>%s</td><td>%d%%</td><td>%s %s</td></tr>',
                $milestone['name'],
                $milestone['percentage'],
                $quotation->currency,
                number_format($amount, 2)
            );
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Generate estimations breakdown HTML
     */
    private function generateEstimationsBreakdown(Quotation $quotation): string
    {
        $estimations = $quotation->estimations;
        
        $html = '<table class="table">';
        $html .= '<thead><tr><th>Service Type</th><th>Hours</th><th>Rate</th><th>Subtotal</th></tr></thead>';
        $html .= '<tbody>';

        $total = 0;
        foreach ($estimations as $type => $hours) {
            $subtotal = $hours * $quotation->hourly_rate;
            $total += $subtotal;
            
            $html .= sprintf(
                '<tr><td>%s</td><td>%d</td><td>%s %s</td><td>%s %s</td></tr>',
                ucfirst(str_replace('_', ' ', $type)),
                $hours,
                $quotation->currency,
                number_format($quotation->hourly_rate, 2),
                $quotation->currency,
                number_format($subtotal, 2)
            );
        }

        // Add discount row if applicable
        if ($quotation->discount_percentage > 0) {
            $discountAmount = ($total * $quotation->discount_percentage) / 100;
            $html .= sprintf(
                '<tr><td colspan="3"><strong>Discount (%s%%)</strong></td><td><strong>-%s %s</strong></td></tr>',
                number_format($quotation->discount_percentage, 2),
                $quotation->currency,
                number_format($discountAmount, 2)
            );
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Activate project when contract is signed
     */
    private function activateProject(Contract $contract): void
    {
        $quotation = $contract->quotation;

        // Check if project already exists
        if ($contract->project_id) {
            // Update existing project status to in progress
            $project = $contract->project;
            $project->update([
                'status' => ProjectStatus::IN_PROGRESS,
                'updated_by' => auth()->id(),
            ]);
        } else {
            // Create new project from quotation using conversion service
            $project = $this->projectConversionService->convertQuotation($quotation->id, [
                'title' => "Project for {$quotation->lead->name}",
                'description' => 'Project created from signed contract',
            ]);
            
            // Update project status to in progress
            $project->update([
                'status' => ProjectStatus::IN_PROGRESS,
                'updated_by' => auth()->id(),
            ]);
            
            // Link contract to project
            $contract->update(['project_id' => $project->id]);
        }
    }
}