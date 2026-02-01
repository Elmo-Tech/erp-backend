<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get sales performance metrics including conversion rates and pipeline health
     */
    public function getSalesPerformance(): array
    {
        return Cache::remember('analytics.sales_performance', 3600, function () {
            // Overall conversion metrics
            $totalLeads = Lead::count();
            $convertedLeads = Lead::where('status', LeadStatus::WON)->count();
            $overallConversionRate = $totalLeads > 0 ? ($convertedLeads / $totalLeads) * 100 : 0;

            // Conversion rates by sales rep
            $conversionByRep = User::select('users.id', 'users.name')
                ->selectRaw('COUNT(leads.id) as total_leads')
                ->selectRaw('COUNT(CASE WHEN leads.status = ? THEN 1 END) as won_leads', [LeadStatus::WON->value])
                ->selectRaw('CASE WHEN COUNT(leads.id) > 0 THEN (COUNT(CASE WHEN leads.status = ? THEN 1 END) / COUNT(leads.id)) * 100 ELSE 0 END as conversion_rate', [LeadStatus::WON->value])
                ->leftJoin('leads', function ($join) {
                    $join->on('users.id', '=', 'leads.reference_id')
                         ->where('leads.reference_type', 'sales_rep');
                })
                ->groupBy('users.id', 'users.name')
                ->having('total_leads', '>', 0)
                ->orderBy('conversion_rate', 'desc')
                ->get();

            // Pipeline health - leads by status
            $pipelineHealth = Lead::select('status')
                ->selectRaw('COUNT(*) as count')
                ->whereNotIn('status', [LeadStatus::WON, LeadStatus::LOST])
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    return [
                        'status' => $item->status->value,
                        'statusLabel' => $item->status->label(),
                        'count' => $item->count,
                    ];
                });

            // Monthly performance trend (last 12 months)
            $monthlyTrend = Lead::select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as total_leads'),
                    DB::raw('COUNT(CASE WHEN status = "' . LeadStatus::WON->value . '" THEN 1 END) as won_leads')
                )
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => $item->month,
                        'totalLeads' => $item->total_leads,
                        'wonLeads' => $item->won_leads,
                        'conversionRate' => $item->total_leads > 0 ? ($item->won_leads / $item->total_leads) * 100 : 0,
                    ];
                });

            // Average deal size
            $averageDealSize = Contract::avg('total_amount') ?? 0;

            // Sales velocity (average days from lead to won)
            $salesVelocity = Lead::where('status', LeadStatus::WON)
                ->whereNotNull('converted_at')
                ->selectRaw('AVG(DATEDIFF(converted_at, created_at)) as avg_days')
                ->value('avg_days') ?? 0;

            return [
                'overallMetrics' => [
                    'totalLeads' => $totalLeads,
                    'convertedLeads' => $convertedLeads,
                    'conversionRate' => round($overallConversionRate, 2),
                    'averageDealSize' => round($averageDealSize, 2),
                    'salesVelocity' => round($salesVelocity, 1),
                ],
                'conversionByRep' => $conversionByRep,
                'pipelineHealth' => $pipelineHealth,
                'monthlyTrend' => $monthlyTrend,
            ];
        });
    }

    /**
     * Get rejection analysis - why we lose deals
     */
    public function getRejectionAnalysis(): array
    {
        return Cache::remember('analytics.rejection_analysis', 3600, function () {
            // Rejection reasons from quotations
            $rejectionReasons = Quotation::select('rejection_reason')
                ->selectRaw('COUNT(*) as count')
                ->where('status', QuotationStatus::REJECTED)
                ->whereNotNull('rejection_reason')
                ->groupBy('rejection_reason')
                ->get()
                ->map(function ($item) {
                    return [
                        'reason' => $item->rejection_reason->value,
                        'reasonLabel' => $item->rejection_reason->label(),
                        'count' => $item->count,
                    ];
                });

            // Lost leads analysis
            $lostLeads = Lead::select('loss_reason')
                ->selectRaw('COUNT(*) as count')
                ->where('status', LeadStatus::LOST)
                ->whereNotNull('loss_reason')
                ->groupBy('loss_reason')
                ->get();

            // Rejection by industry sector
            $rejectionBySector = Quotation::select('leads.industry_sector')
                ->selectRaw('COUNT(*) as total_quotations')
                ->selectRaw('COUNT(CASE WHEN quotations.status = ? THEN 1 END) as rejected_count', [QuotationStatus::REJECTED->value])
                ->selectRaw('CASE WHEN COUNT(*) > 0 THEN (COUNT(CASE WHEN quotations.status = ? THEN 1 END) / COUNT(*)) * 100 ELSE 0 END as rejection_rate', [QuotationStatus::REJECTED->value])
                ->join('leads', 'quotations.lead_id', '=', 'leads.id')
                ->whereNotNull('leads.industry_sector')
                ->groupBy('leads.industry_sector')
                ->orderBy('rejection_rate', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'sector' => $item->industry_sector->value,
                        'sectorLabel' => $item->industry_sector->label(),
                        'totalQuotations' => $item->total_quotations,
                        'rejectedCount' => $item->rejected_count,
                        'rejectionRate' => round($item->rejection_rate, 2),
                    ];
                });

            // Monthly rejection trend
            $monthlyRejectionTrend = Quotation::select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as total_quotations'),
                    DB::raw('COUNT(CASE WHEN status = "' . QuotationStatus::REJECTED->value . '" THEN 1 END) as rejected_quotations')
                )
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => $item->month,
                        'totalQuotations' => $item->total_quotations,
                        'rejectedQuotations' => $item->rejected_quotations,
                        'rejectionRate' => $item->total_quotations > 0 ? ($item->rejected_quotations / $item->total_quotations) * 100 : 0,
                    ];
                });

            return [
                'rejectionReasons' => $rejectionReasons,
                'lostLeads' => $lostLeads,
                'rejectionBySector' => $rejectionBySector,
                'monthlyTrend' => $monthlyRejectionTrend,
                'summary' => [
                    'totalRejectedQuotations' => Quotation::where('status', QuotationStatus::REJECTED)->count(),
                    'totalLostLeads' => Lead::where('status', LeadStatus::LOST)->count(),
                    'overallRejectionRate' => $this->calculateOverallRejectionRate(),
                ],
            ];
        });
    }

    /**
     * Get cashflow forecast including receivables and projected revenue
     */
    public function getCashflowForecast(): array
    {
        return Cache::remember('analytics.cashflow_forecast', 1800, function () { // 30 minutes cache
            // Current receivables (unpaid invoices)
            $receivables = Invoice::whereIn('status', [InvoiceStatus::SENT, InvoiceStatus::OVERDUE])
                ->sum('amount');

            // Overdue receivables
            $overdueReceivables = Invoice::where('status', InvoiceStatus::OVERDUE)
                ->sum('amount');

            // Projected revenue from active contracts (unbilled amounts)
            $projectedRevenue = Contract::select('contracts.*')
                ->selectRaw('COALESCE(SUM(invoices.amount), 0) as invoiced_amount')
                ->selectRaw('contracts.total_amount - COALESCE(SUM(invoices.amount), 0) as unbilled_amount')
                ->leftJoin('invoices', 'contracts.id', '=', 'invoices.contract_id')
                ->whereIn('contracts.status', ['signed', 'active'])
                ->groupBy('contracts.id')
                ->havingRaw('unbilled_amount > 0')
                ->get()
                ->sum('unbilled_amount');

            // Revenue by industry sector
            $revenueBySector = Contract::select('leads.industry_sector')
                ->selectRaw('SUM(contracts.total_amount) as total_revenue')
                ->selectRaw('COUNT(*) as contract_count')
                ->join('quotations', 'contracts.quotation_id', '=', 'quotations.id')
                ->join('leads', 'quotations.lead_id', '=', 'leads.id')
                ->whereNotNull('leads.industry_sector')
                ->whereIn('contracts.status', ['signed', 'active', 'completed'])
                ->groupBy('leads.industry_sector')
                ->orderBy('total_revenue', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'sector' => $item->industry_sector->value,
                        'sectorLabel' => $item->industry_sector->label(),
                        'totalRevenue' => round($item->total_revenue, 2),
                        'contractCount' => $item->contract_count,
                        'averageContractValue' => round($item->total_revenue / $item->contract_count, 2),
                    ];
                });

            // Monthly cashflow projection (next 12 months)
            $monthlyCashflow = Invoice::select(
                    DB::raw('DATE_FORMAT(due_date, "%Y-%m") as month'),
                    DB::raw('SUM(amount) as expected_amount')
                )
                ->whereIn('status', [InvoiceStatus::SENT, InvoiceStatus::OVERDUE])
                ->where('due_date', '>=', now())
                ->where('due_date', '<=', now()->addMonths(12))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => $item->month,
                        'expectedAmount' => round($item->expected_amount, 2),
                    ];
                });

            // Payment trends (last 6 months)
            $paymentTrends = Invoice::select(
                    DB::raw('DATE_FORMAT(paid_at, "%Y-%m") as month'),
                    DB::raw('SUM(amount) as paid_amount'),
                    DB::raw('COUNT(*) as paid_count')
                )
                ->where('status', InvoiceStatus::PAID)
                ->where('paid_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => $item->month,
                        'paidAmount' => round($item->paid_amount, 2),
                        'paidCount' => $item->paid_count,
                    ];
                });

            return [
                'currentFinancials' => [
                    'receivables' => round($receivables, 2),
                    'overdueReceivables' => round($overdueReceivables, 2),
                    'projectedRevenue' => round($projectedRevenue, 2),
                    'totalPipeline' => round($receivables + $projectedRevenue, 2),
                ],
                'revenueBySector' => $revenueBySector,
                'monthlyCashflow' => $monthlyCashflow,
                'paymentTrends' => $paymentTrends,
                'summary' => [
                    'totalContracts' => Contract::whereIn('status', ['signed', 'active', 'completed'])->count(),
                    'activeContracts' => Contract::whereIn('status', ['signed', 'active'])->count(),
                    'totalInvoices' => Invoice::count(),
                    'paidInvoices' => Invoice::where('status', InvoiceStatus::PAID)->count(),
                ],
            ];
        });
    }

    /**
     * Get follow-up radar - items needing immediate attention
     */
    public function getFollowUpRadar(): array
    {
        return Cache::remember('analytics.follow_up_radar', 900, function () { // 15 minutes cache
            // Quotations expiring in < 3 days
            $expiringQuotations = Quotation::with(['lead'])
                ->where('status', QuotationStatus::SENT)
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '>', now())
                ->where('expiry_date', '<=', now()->addDays(3))
                ->orderBy('expiry_date')
                ->get()
                ->map(function ($quotation) {
                    return [
                        'type' => 'expiring_quotation',
                        'id' => $quotation->id,
                        'title' => "Quotation {$quotation->quotation_number}",
                        'description' => "For {$quotation->lead->name} - Expires " . $quotation->expiry_date->format('M j, Y'),
                        'urgency' => $quotation->expiry_date->diffInDays(now()) <= 1 ? 'critical' : 'high',
                        'daysLeft' => $quotation->expiry_date->diffInDays(now()),
                        'amount' => $quotation->final_price,
                        'leadName' => $quotation->lead->name,
                        'expiryDate' => $quotation->expiry_date->toISOString(),
                    ];
                });

            // Leads stuck in negotiation for > 14 days without activity
            $staleLeads = Lead::with(['activities'])
                ->where('status', LeadStatus::NEGOTIATION)
                ->where('updated_at', '<=', now()->subDays(14))
                ->get()
                ->filter(function ($lead) {
                    // Check if there's been any recent activity
                    $lastActivity = $lead->activities()->latest()->first();
                    return !$lastActivity || $lastActivity->created_at <= now()->subDays(14);
                })
                ->map(function ($lead) {
                    $daysSinceUpdate = $lead->updated_at->diffInDays(now());
                    return [
                        'type' => 'stale_lead',
                        'id' => $lead->id,
                        'title' => "Lead: {$lead->name}",
                        'description' => "In negotiation for {$daysSinceUpdate} days without activity",
                        'urgency' => $daysSinceUpdate > 30 ? 'critical' : ($daysSinceUpdate > 21 ? 'high' : 'medium'),
                        'daysSinceUpdate' => $daysSinceUpdate,
                        'expectedBudget' => $lead->expected_budget,
                        'company' => $lead->company,
                        'lastUpdated' => $lead->updated_at->toISOString(),
                    ];
                });

            // Overdue invoices
            $overdueInvoices = Invoice::with(['project', 'contract'])
                ->where('status', InvoiceStatus::OVERDUE)
                ->orWhere(function ($query) {
                    $query->where('status', InvoiceStatus::SENT)
                          ->where('due_date', '<', now());
                })
                ->orderBy('due_date')
                ->get()
                ->map(function ($invoice) {
                    $daysOverdue = now()->diffInDays($invoice->due_date);
                    return [
                        'type' => 'overdue_invoice',
                        'id' => $invoice->id,
                        'title' => "Invoice {$invoice->invoice_number}",
                        'description' => "Overdue by {$daysOverdue} days",
                        'urgency' => $daysOverdue > 30 ? 'critical' : ($daysOverdue > 14 ? 'high' : 'medium'),
                        'daysOverdue' => $daysOverdue,
                        'amount' => $invoice->amount,
                        'projectName' => $invoice->project?->name ?? 'N/A',
                        'dueDate' => $invoice->due_date->toISOString(),
                    ];
                });

            // Leads needing follow-up
            $followUpLeads = Lead::where('status', '!=', LeadStatus::WON)
                ->where('status', '!=', LeadStatus::LOST)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now())
                ->orderBy('next_follow_up_at')
                ->get()
                ->map(function ($lead) {
                    $daysOverdue = now()->diffInDays($lead->next_follow_up_at);
                    return [
                        'type' => 'follow_up_needed',
                        'id' => $lead->id,
                        'title' => "Follow-up: {$lead->name}",
                        'description' => "Follow-up was due " . $lead->next_follow_up_at->format('M j, Y'),
                        'urgency' => $daysOverdue > 7 ? 'high' : 'medium',
                        'daysOverdue' => $daysOverdue,
                        'company' => $lead->company,
                        'status' => $lead->status->label(),
                        'followUpDate' => $lead->next_follow_up_at->toISOString(),
                    ];
                });

            // Combine all items and sort by urgency
            $allItems = collect()
                ->merge($expiringQuotations)
                ->merge($staleLeads)
                ->merge($overdueInvoices)
                ->merge($followUpLeads)
                ->sortBy(function ($item) {
                    // Sort by urgency: critical = 1, high = 2, medium = 3
                    return match ($item['urgency']) {
                        'critical' => 1,
                        'high' => 2,
                        'medium' => 3,
                        default => 4,
                    };
                })
                ->values();

            return [
                'items' => $allItems,
                'summary' => [
                    'totalItems' => $allItems->count(),
                    'criticalItems' => $allItems->where('urgency', 'critical')->count(),
                    'highPriorityItems' => $allItems->where('urgency', 'high')->count(),
                    'mediumPriorityItems' => $allItems->where('urgency', 'medium')->count(),
                ],
                'breakdown' => [
                    'expiringQuotations' => $expiringQuotations->count(),
                    'staleLeads' => $staleLeads->count(),
                    'overdueInvoices' => $overdueInvoices->count(),
                    'followUpNeeded' => $followUpLeads->count(),
                ],
            ];
        });
    }

    /**
     * Calculate overall rejection rate
     */
    private function calculateOverallRejectionRate(): float
    {
        $totalQuotations = Quotation::count();
        $rejectedQuotations = Quotation::where('status', QuotationStatus::REJECTED)->count();
        
        return $totalQuotations > 0 ? ($rejectedQuotations / $totalQuotations) * 100 : 0;
    }

    /**
     * Clear all analytics cache
     */
    public function clearCache(): void
    {
        Cache::forget('analytics.sales_performance');
        Cache::forget('analytics.rejection_analysis');
        Cache::forget('analytics.cashflow_forecast');
        Cache::forget('analytics.follow_up_radar');
    }
}