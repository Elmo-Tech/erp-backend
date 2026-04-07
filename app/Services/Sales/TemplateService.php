<?php

namespace App\Services\Sales;

use App\Models\ContractTemplate;
use App\Models\ProposalTemplate;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TemplateService
{
    /**
     * Get active proposal templates
     */
    public function getProposalTemplates(?string $type = null): Collection
    {
        $query = ProposalTemplate::active();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get active contract templates
     */
    public function getContractTemplates(?string $type = null): Collection
    {
        $query = ContractTemplate::active();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Create proposal template
     */
    public function createProposalTemplate(array $data, User $user): ProposalTemplate
    {
        return ProposalTemplate::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'content' => $data['content'],
            'placeholders' => $data['placeholders'] ?? [],
            'is_active' => $data['isActive'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Create contract template
     */
    public function createContractTemplate(array $data, User $user): ContractTemplate
    {
        return ContractTemplate::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'content' => $data['content'],
            'placeholders' => $data['placeholders'] ?? [],
            'is_active' => $data['isActive'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Generate content from proposal template
     */
    public function generateProposalContent(ProposalTemplate $template, Quotation $quotation, array $customValues = []): string
    {
        $lead = $quotation->lead;

        $replacements = [
            '{{clientName}}' => $lead->name,
            '{{clientCompany}}' => $lead->company ?? '',
            '{{clientEmail}}' => $lead->email,
            '{{quotationNumber}}' => $quotation->quotation_number ?? '',
            '{{totalPrice}}' => number_format($quotation->final_price, 2),
            '{{currency}}' => $quotation->currency,
            '{{date}}' => now()->format('Y-m-d'),
            '{{expiryDate}}' => $quotation->expiry_date?->format('Y-m-d') ?? '',
        ];

        // Add custom values
        foreach ($customValues as $key => $value) {
            $replacements["{{{$key}}}"] = $value;
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->content
        );
    }

    /**
     * Generate content from contract template
     */
    public function generateContractContent(
        ContractTemplate $template,
        array $partyOne,
        array $partyTwo,
        float $totalAmount,
        string $currency,
        array $customValues = []
    ): string {
        $replacements = [
            '{{partyOneName}}' => $partyOne['company'] ?? '',
            '{{partyOneRepresentative}}' => $partyOne['representative'] ?? '',
            '{{partyOneTitle}}' => $partyOne['title'] ?? '',
            '{{partyOneAddress}}' => $partyOne['address'] ?? '',
            '{{partyTwoName}}' => $partyTwo['company'] ?? $partyTwo['name'] ?? '',
            '{{partyTwoRepresentative}}' => $partyTwo['representative'] ?? '',
            '{{partyTwoTitle}}' => $partyTwo['title'] ?? '',
            '{{partyTwoAddress}}' => $partyTwo['address'] ?? '',
            '{{totalAmount}}' => number_format($totalAmount, 2),
            '{{currency}}' => $currency,
            '{{date}}' => now()->format('Y-m-d'),
        ];

        // Add custom values
        foreach ($customValues as $key => $value) {
            $replacements["{{{$key}}}"] = $value;
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->content
        );
    }

    /**
     * Update proposal template
     */
    public function updateProposalTemplate(ProposalTemplate $template, array $data, User $user): ProposalTemplate
    {
        $template->update([
            'name' => $data['name'] ?? $template->name,
            'type' => $data['type'] ?? $template->type,
            'content' => $data['content'] ?? $template->content,
            'placeholders' => $data['placeholders'] ?? $template->placeholders,
            'is_active' => $data['isActive'] ?? $template->is_active,
            'updated_by' => $user->id,
        ]);

        return $template->fresh();
    }

    /**
     * Update contract template
     */
    public function updateContractTemplate(ContractTemplate $template, array $data, User $user): ContractTemplate
    {
        $template->update([
            'name' => $data['name'] ?? $template->name,
            'type' => $data['type'] ?? $template->type,
            'content' => $data['content'] ?? $template->content,
            'placeholders' => $data['placeholders'] ?? $template->placeholders,
            'is_active' => $data['isActive'] ?? $template->is_active,
            'updated_by' => $user->id,
        ]);

        return $template->fresh();
    }
}
