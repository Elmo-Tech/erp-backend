<?php

namespace App\Modules\Template\Actions;

use App\Modules\Template\Models\ProposalTemplate;
use App\Modules\Contract\Models\ContractTemplate;
use App\Models\Quotation;

class RenderTemplateAction
{
    public function renderProposal(ProposalTemplate $template, Quotation $quotation, array $customValues = []): string
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

        foreach ($customValues as $key => $value) {
            $replacements["{{{$key}}}"] = $value;
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->content
        );
    }

    public function renderContract(
        ContractTemplate $template,
        array $partyOne,
        array $partyTwo,
        float $totalAmount,
        string $currency = 'SAR',
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

        foreach ($customValues as $key => $value) {
            $replacements["{{{$key}}}"] = $value;
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->content
        );
    }
}
