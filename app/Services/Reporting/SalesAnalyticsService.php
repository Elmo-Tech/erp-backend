<?php

namespace App\Services\Reporting;

use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Lead;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesAnalyticsService
{
    /**
     * Get sales performance by user
     */
    public function getSalesPerformance(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $salesReps = DB::table('leads')
            ->select([
                'reference_id as user_id',
                DB::raw('COUNT(*) as leads_count'),
                DB::raw('SUM(CASE WHEN status = "won" THEN 1 ELSE 0 END) as converted_count'),
                DB::raw('SUM(expected_budget) as total_expected_value'),
            ])
            ->where('reference_type', 'sales_rep')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('reference_id')
            ->get();

        // Get actual values from quotations
        $quotationValues = DB::table('quotations')
            ->join('leads', 'quotations.lead_id', '=', 'leads.id')
            ->select([
                'leads.reference_id as user_id',
                DB::raw('SUM(quotations.final_price) as total_value'),
                DB::raw('SUM(quotations.commission_amount) as total_commission'),
            ])
            ->where('leads.reference_type', 'sales_rep')
            ->where('quotations.status', QuotationStatus::APPROVED->value)
            ->whereBetween('quotations.created_at', [$startDate, $endDate])
            ->groupBy('leads.reference_id')
            ->get()
            ->keyBy('user_id');

        return $salesReps->map(function ($rep) use ($quotationValues) {
            $values = $quotationValues->get($rep->user_id);
            $conversionRate = $rep->leads_count > 0
                ? round(($rep->converted_count / $rep->leads_count) * 100, 2)
                : 0;

            return [
                'userId' => $rep->user_id,
                'leadsCount' => $rep->leads_count,
                'convertedCount' => $rep->converted_count,
                'conversionRate' => $conversionRate,
                'totalValue' => $values->total_value ?? 0,
                'totalCommission' => $values->total_commission ?? 0,
            ];
        })->values()->toArray();
    }

    /**
     * Get leads by source analytics
     */
    public function getSourceAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $sources = DB::table('leads')
            ->select([
                'source',
                DB::raw('COUNT(*) as leads_count'),
                DB::raw('SUM(CASE WHEN status = "won" THEN 1 ELSE 0 END) as converted_count'),
                DB::raw('SUM(expected_budget) as total_expected_value'),
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('source')
            ->get();

        // Get actual values
        $sourceValues = DB::table('quotations')
            ->join('leads', 'quotations.lead_id', '=', 'leads.id')
            ->select([
                'leads.source',
                DB::raw('SUM(quotations.final_price) as total_value'),
            ])
            ->where('quotations.status', QuotationStatus::APPROVED->value)
            ->whereBetween('quotations.created_at', [$startDate, $endDate])
            ->groupBy('leads.source')
            ->get()
            ->keyBy('source');

        return $sources->map(function ($source) use ($sourceValues) {
            $values = $sourceValues->get($source->source);
            $conversionRate = $source->leads_count > 0
                ? round(($source->converted_count / $source->leads_count) * 100, 2)
                : 0;
            $totalValue = $values->total_value ?? 0;
            $avgDealSize = $source->converted_count > 0
                ? round($totalValue / $source->converted_count, 2)
                : 0;

            return [
                'source' => $source->source,
                'leadsCount' => $source->leads_count,
                'convertedCount' => $source->converted_count,
                'conversionRate' => $conversionRate,
                'totalValue' => $totalValue,
                'avgDealSize' => $avgDealSize,
            ];
        })->values()->toArray();
    }

    /**
     * Get sales pipeline
     */
    public function getPipeline(): array
    {
        $stages = [];

        foreach (LeadStatus::cases() as $status) {
            if (in_array($status, [LeadStatus::WON, LeadStatus::LOST])) {
                continue;
            }

            $leads = Lead::where('status', $status)->get();

            $stages[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $leads->count(),
                'value' => $leads->sum('expected_budget'),
            ];
        }

        return $stages;
    }

    /**
     * Get revenue forecast based on milestones
     */
    public function getRevenueForecast(): array
    {
        $thisMonth = now()->startOfMonth();
        $nextMonth = now()->addMonth()->startOfMonth();
        $thisQuarter = now()->startOfQuarter();
        $nextQuarter = now()->addQuarter()->startOfQuarter();

        $milestonesDue = ContractMilestone::with('contract')
            ->whereIn('status', ['pending', 'due'])
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addMonths(3))
            ->orderBy('due_date')
            ->get();

        $thisMonthForecast = $milestonesDue
            ->whereBetween('due_date', [$thisMonth, $thisMonth->copy()->endOfMonth()])
            ->sum('amount');

        $nextMonthForecast = $milestonesDue
            ->whereBetween('due_date', [$nextMonth, $nextMonth->copy()->endOfMonth()])
            ->sum('amount');

        $thisQuarterForecast = $milestonesDue
            ->whereBetween('due_date', [$thisQuarter, $thisQuarter->copy()->endOfQuarter()])
            ->sum('amount');

        return [
            'milestonesDue' => $milestonesDue->take(10)->map(fn($m) => [
                'milestoneId' => $m->id,
                'contractId' => $m->contract_id,
                'projectName' => $m->contract->title ?? '',
                'milestoneTitle' => $m->title,
                'amount' => $m->amount,
                'dueDate' => $m->due_date->format('Y-m-d'),
                'status' => $m->status->value,
            ])->values()->toArray(),
            'forecast' => [
                'thisMonth' => round($thisMonthForecast, 2),
                'nextMonth' => round($nextMonthForecast, 2),
                'thisQuarter' => round($thisQuarterForecast, 2),
            ],
        ];
    }

    /**
     * Get quotation statistics
     */
    public function getQuotationStats(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $quotations = Quotation::whereBetween('created_at', [$startDate, $endDate])->get();

        $byStatus = [];
        foreach (QuotationStatus::cases() as $status) {
            $filtered = $quotations->where('status', $status);
            $byStatus[$status->value] = [
                'count' => $filtered->count(),
                'value' => $filtered->sum('final_price'),
            ];
        }

        $rejectionReasons = $quotations
            ->where('status', QuotationStatus::REJECTED)
            ->whereNotNull('rejection_reason')
            ->groupBy('rejection_reason')
            ->map(fn($group) => $group->count())
            ->toArray();

        return [
            'total' => $quotations->count(),
            'totalValue' => $quotations->sum('final_price'),
            'avgValue' => $quotations->count() > 0 ? round($quotations->avg('final_price'), 2) : 0,
            'byStatus' => $byStatus,
            'rejectionReasons' => $rejectionReasons,
            'conversionRate' => $quotations->count() > 0
                ? round(($byStatus[QuotationStatus::APPROVED->value]['count'] / $quotations->count()) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get project types breakdown
     */
    public function getProjectTypesBreakdown(): array
    {
        // This would need a project_type field on projects or quotations
        // For now, using template types from quotations
        $types = DB::table('quotations')
            ->join('proposal_templates', 'quotations.template_id', '=', 'proposal_templates.id')
            ->select([
                'proposal_templates.type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quotations.final_price) as total_value'),
                DB::raw('AVG(quotations.final_price) as avg_value'),
            ])
            ->where('quotations.status', QuotationStatus::APPROVED->value)
            ->groupBy('proposal_templates.type')
            ->get();

        $total = $types->sum('count');

        return $types->map(function ($type) use ($total) {
            return [
                'type' => $type->type,
                'count' => $type->count,
                'percentage' => $total > 0 ? round(($type->count / $total) * 100, 2) : 0,
                'totalValue' => round($type->total_value, 2),
                'avgValue' => round($type->avg_value, 2),
            ];
        })->values()->toArray();
    }
}
