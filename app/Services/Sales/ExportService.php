<?php

namespace App\Services\Sales;

use App\Enums\LeadStatus;
use App\Enums\ProposalStatus;
use App\Enums\QuotationStatus;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExportService
{
    /**
     * Export leads to array format
     */
    public function exportLeads(array $filters = []): array
    {
        $query = Lead::with(['techLead:id,name', 'client:id,name']);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['startDate'])) {
            $query->whereDate('created_at', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->whereDate('created_at', '<=', $filters['endDate']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        $leads = $query->orderBy('created_at', 'desc')->get();

        return [
            'headers' => [
                'ID',
                'Name',
                'Email',
                'Company',
                'Phone',
                'Source',
                'Status',
                'Priority',
                'Temperature',
                'Expected Budget',
                'Tech Lead',
                'Created At',
                'Last Contact',
            ],
            'data' => $leads->map(fn($lead) => [
                $lead->id,
                $lead->name,
                $lead->email,
                $lead->company,
                $lead->phone,
                $lead->source?->value,
                $lead->status?->value,
                $lead->priority?->value,
                $lead->lead_temperature?->value,
                $lead->expected_budget,
                $lead->techLead?->name,
                $lead->created_at->format('Y-m-d H:i'),
                $lead->last_contact_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'filename' => 'leads_export_' . now()->format('Y-m-d_His'),
            'totalCount' => $leads->count(),
        ];
    }

    /**
     * Export proposals to array format
     */
    public function exportProposals(array $filters = []): array
    {
        $query = Proposal::with(['lead:id,name,company', 'techLead:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['startDate'])) {
            $query->whereDate('created_at', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->whereDate('created_at', '<=', $filters['endDate']);
        }

        $proposals = $query->orderBy('created_at', 'desc')->get();

        return [
            'headers' => [
                'ID',
                'Proposal Number',
                'Title',
                'Lead',
                'Company',
                'Status',
                'Tech Lead',
                'Sent At',
                'Expiry Date',
                'Created At',
            ],
            'data' => $proposals->map(fn($p) => [
                $p->id,
                $p->proposal_number,
                $p->title,
                $p->lead?->name,
                $p->lead?->company,
                $p->status?->value,
                $p->techLead?->name,
                $p->sent_at?->format('Y-m-d'),
                $p->expiry_date?->format('Y-m-d'),
                $p->created_at->format('Y-m-d H:i'),
            ])->toArray(),
            'filename' => 'proposals_export_' . now()->format('Y-m-d_His'),
            'totalCount' => $proposals->count(),
        ];
    }

    /**
     * Export quotations to array format
     */
    public function exportQuotations(array $filters = []): array
    {
        $query = Quotation::with(['lead:id,name,company']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['startDate'])) {
            $query->whereDate('created_at', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->whereDate('created_at', '<=', $filters['endDate']);
        }

        $quotations = $query->orderBy('created_at', 'desc')->get();

        return [
            'headers' => [
                'ID',
                'Quotation Number',
                'Lead',
                'Company',
                'Status',
                'Hourly Rate',
                'Discount %',
                'Final Price',
                'Currency',
                'Valid Until',
                'Created At',
            ],
            'data' => $quotations->map(fn($q) => [
                $q->id,
                $q->quotation_number,
                $q->lead?->name,
                $q->lead?->company,
                $q->status?->value,
                $q->hourly_rate,
                $q->discount_percentage,
                $q->final_price,
                $q->currency,
                $q->valid_until?->format('Y-m-d'),
                $q->created_at->format('Y-m-d H:i'),
            ])->toArray(),
            'filename' => 'quotations_export_' . now()->format('Y-m-d_His'),
            'totalCount' => $quotations->count(),
        ];
    }

    /**
     * Generate sales report
     */
    public function generateSalesReport(Carbon $startDate, Carbon $endDate): array
    {
        // Leads stats
        $leadsCreated = Lead::whereBetween('created_at', [$startDate, $endDate])->count();
        $leadsWon = Lead::where('status', LeadStatus::WON)
            ->whereBetween('converted_at', [$startDate, $endDate])
            ->count();
        $leadsLost = Lead::where('status', LeadStatus::LOST)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->count();

        // Proposals stats
        $proposalsCreated = Proposal::whereBetween('created_at', [$startDate, $endDate])->count();
        $proposalsApproved = Proposal::where('status', ProposalStatus::APPROVED)
            ->whereBetween('approved_at', [$startDate, $endDate])
            ->count();
        $proposalsRejected = Proposal::where('status', ProposalStatus::REJECTED)
            ->whereBetween('rejected_at', [$startDate, $endDate])
            ->count();

        // Quotations stats
        $quotationsCreated = Quotation::whereBetween('created_at', [$startDate, $endDate])->count();
        $quotationsApproved = Quotation::where('status', QuotationStatus::APPROVED)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->count();
        $quotationsTotalValue = Quotation::where('status', QuotationStatus::APPROVED)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->sum('final_price');

        // Conversion rates
        $leadConversionRate = $leadsCreated > 0 ? round(($leadsWon / $leadsCreated) * 100, 1) : 0;
        $proposalApprovalRate = $proposalsCreated > 0 ? round(($proposalsApproved / $proposalsCreated) * 100, 1) : 0;
        $quotationApprovalRate = $quotationsCreated > 0 ? round(($quotationsApproved / $quotationsCreated) * 100, 1) : 0;

        return [
            'period' => [
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ],
            'leads' => [
                'created' => $leadsCreated,
                'won' => $leadsWon,
                'lost' => $leadsLost,
                'conversionRate' => $leadConversionRate,
            ],
            'proposals' => [
                'created' => $proposalsCreated,
                'approved' => $proposalsApproved,
                'rejected' => $proposalsRejected,
                'approvalRate' => $proposalApprovalRate,
            ],
            'quotations' => [
                'created' => $quotationsCreated,
                'approved' => $quotationsApproved,
                'totalValue' => $quotationsTotalValue,
                'approvalRate' => $quotationApprovalRate,
            ],
            'generatedAt' => now()->toISOString(),
        ];
    }
}
