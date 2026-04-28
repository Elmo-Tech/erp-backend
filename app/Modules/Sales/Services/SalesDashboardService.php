<?php

namespace App\Modules\Sales\Services;

use App\Enums\ActivityStatus;
use App\Enums\LeadStatus;
use App\Enums\MeetingStatus;
use App\Enums\ProposalStatus;
use App\Enums\QuotationStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadActivity;
use App\Modules\Lead\Models\LeadMeeting;
use App\Modules\Proposal\Models\Proposal;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesDashboardService
{
    /**
     * Get pipeline health overview
     */
    public function getPipelineHealth(): array
    {
        // Leads by status
        $leadsByStatus = Lead::select('status', DB::raw('count(*) as count'))
            ->whereNotIn('status', [LeadStatus::WON, LeadStatus::LOST])
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->status->value => $item->count]);

        // Proposals by status
        $proposalsByStatus = Proposal::select('status', DB::raw('count(*) as count'))
            ->whereNotIn('status', [ProposalStatus::APPROVED, ProposalStatus::REJECTED, ProposalStatus::EXPIRED])
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->status->value => $item->count]);

        // Quotations by status with values
        $quotationStats = Quotation::select(
            'status',
            DB::raw('count(*) as count'),
            DB::raw('sum(final_price) as total_value')
        )
            ->whereNotIn('status', [QuotationStatus::APPROVED, QuotationStatus::REJECTED])
            ->groupBy('status')
            ->get();

        $quotationsByStatus = $quotationStats->mapWithKeys(fn ($item) => [
            $item->status->value => [
                'count' => $item->count,
                'totalValue' => (float) $item->total_value,
            ],
        ]);

        // Total pending value
        $totalPendingValue = $quotationStats->sum('total_value');

        // Conversion rates
        $totalLeads = Lead::count();
        $wonLeads = Lead::where('status', LeadStatus::WON)->count();
        $lostLeads = Lead::where('status', LeadStatus::LOST)->count();
        $conversionRate = $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 1) : 0;
        $lossRate = $totalLeads > 0 ? round(($lostLeads / $totalLeads) * 100, 1) : 0;

        return [
            'leads' => [
                'byStatus' => $leadsByStatus,
                'total' => Lead::whereNotIn('status', [LeadStatus::WON, LeadStatus::LOST])->count(),
            ],
            'proposals' => [
                'byStatus' => $proposalsByStatus,
                'total' => Proposal::whereNotIn('status', [ProposalStatus::APPROVED, ProposalStatus::REJECTED, ProposalStatus::EXPIRED])->count(),
            ],
            'quotations' => [
                'byStatus' => $quotationsByStatus,
                'total' => Quotation::whereNotIn('status', [QuotationStatus::APPROVED, QuotationStatus::REJECTED])->count(),
                'totalPendingValue' => $totalPendingValue,
            ],
            'conversionRate' => $conversionRate,
            'lossRate' => $lossRate,
        ];
    }

    /**
     * Get team performance metrics
     */
    public function getTeamPerformance(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        // Sales reps performance
        $salesPerformance = User::whereHas('roles', fn ($q) => $q->where('name', 'sales'))
            ->withCount([
                'leads' => fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]),
                'leads as won_leads_count' => fn ($q) => $q->where('status', LeadStatus::WON)
                    ->whereBetween('converted_at', [$startDate, $endDate]),
                'leads as lost_leads_count' => fn ($q) => $q->where('status', LeadStatus::LOST)
                    ->whereBetween('updated_at', [$startDate, $endDate]),
            ])
            ->get()
            ->map(function ($user) {
                $totalHandled = $user->won_leads_count + $user->lost_leads_count;
                $winRate = $totalHandled > 0 ? round(($user->won_leads_count / $totalHandled) * 100, 1) : 0;

                return [
                    'userId' => $user->id,
                    'name' => $user->name,
                    'leadsCreated' => $user->leads_count,
                    'leadsWon' => $user->won_leads_count,
                    'leadsLost' => $user->lost_leads_count,
                    'winRate' => $winRate,
                ];
            });

        // Tech leads performance
        $techLeadPerformance = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['tech-lead', 'senior-developer']))
            ->withCount([
                'techLeadLeads' => fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]),
                'techLeadLeads as successful_proposals_count' => fn ($q) => $q->whereHas('proposals', fn ($pq) => $pq->where('status', ProposalStatus::APPROVED)),
            ])
            ->get()
            ->map(function ($user) {
                $successRate = $user->tech_lead_leads_count > 0
                    ? round(($user->successful_proposals_count / $user->tech_lead_leads_count) * 100, 1)
                    : 0;

                return [
                    'userId' => $user->id,
                    'name' => $user->name,
                    'leadsEvaluated' => $user->tech_lead_leads_count,
                    'successfulProposals' => $user->successful_proposals_count,
                    'successRate' => $successRate,
                ];
            });

        return [
            'salesReps' => $salesPerformance,
            'techLeads' => $techLeadPerformance,
            'period' => [
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Get bottleneck analysis
     */
    public function getBottleneckAnalysis(): array
    {
        // Average time in each stage
        $stageMetrics = [];

        // Leads stage duration
        $leadStages = [
            LeadStatus::NEW->value => 'New',
            LeadStatus::CONTACTED->value => 'Contacted',
            LeadStatus::QUALIFIED->value => 'Qualified',
            LeadStatus::PROPOSAL_SENT->value => 'Proposal Sent',
            LeadStatus::QUOTATION_SENT->value => 'Quotation Sent',
            LeadStatus::NEGOTIATION->value => 'Negotiation',
        ];

        foreach ($leadStages as $status => $label) {
            $count = Lead::where('status', $status)->count();
            $stageMetrics[] = [
                'stage' => $label,
                'status' => $status,
                'count' => $count,
                'type' => 'lead',
            ];
        }

        // Loss reasons analysis
        $lossReasons = Lead::where('status', LeadStatus::LOST)
            ->whereNotNull('loss_reason')
            ->select('loss_reason', DB::raw('count(*) as count'))
            ->groupBy('loss_reason')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'reason' => $item->loss_reason,
                'count' => $item->count,
            ]);

        // Proposal rejection reasons
        $proposalRejections = Proposal::where('status', ProposalStatus::REJECTED)
            ->whereNotNull('rejection_reason')
            ->select('rejection_reason', DB::raw('count(*) as count'))
            ->groupBy('rejection_reason')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'reason' => $item->rejection_reason,
                'count' => $item->count,
            ]);

        // Quotation rejection reasons
        $quotationRejections = Quotation::where('status', QuotationStatus::REJECTED)
            ->whereNotNull('rejection_reason')
            ->select('rejection_reason', DB::raw('count(*) as count'))
            ->groupBy('rejection_reason')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'reason' => $item->rejection_reason?->value ?? $item->rejection_reason,
                'count' => $item->count,
            ]);

        // Stale leads (no activity in 7+ days)
        $staleLeads = Lead::whereNotIn('status', [LeadStatus::WON, LeadStatus::LOST])
            ->where(function ($q) {
                $q->whereNull('last_contact_at')
                    ->orWhere('last_contact_at', '<', now()->subDays(7));
            })
            ->count();

        return [
            'stageMetrics' => $stageMetrics,
            'lossReasons' => $lossReasons,
            'proposalRejections' => $proposalRejections,
            'quotationRejections' => $quotationRejections,
            'staleLeads' => $staleLeads,
        ];
    }

    /**
     * Get alerts for manager
     */
    public function getAlerts(): array
    {
        $alerts = [];

        // Expiring proposals (within 48 hours)
        $expiringProposals = Proposal::where('status', ProposalStatus::SENT)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addHours(48)])
            ->with('lead:id,name,company')
            ->get()
            ->map(fn ($p) => [
                'type' => 'proposal_expiring',
                'severity' => 'warning',
                'title' => __('alerts.proposal_expiring'),
                'message' => __('alerts.proposal_expiring_message', [
                    'title' => $p->title,
                    'lead' => $p->lead?->name,
                    'date' => $p->expiry_date->format('M d, Y'),
                ]),
                'entityType' => 'proposal',
                'entityId' => $p->id,
                'expiresAt' => $p->expiry_date->toISOString(),
            ]);

        // Expiring quotations (within 48 hours)
        $expiringQuotations = Quotation::where('status', QuotationStatus::SENT)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addHours(48)])
            ->with('lead:id,name,company')
            ->get()
            ->map(fn ($q) => [
                'type' => 'quotation_expiring',
                'severity' => 'warning',
                'title' => __('alerts.quotation_expiring'),
                'message' => __('alerts.quotation_expiring_message', [
                    'number' => $q->quotation_number,
                    'lead' => $q->lead?->name,
                    'date' => $q->expiry_date->format('M d, Y'),
                ]),
                'entityType' => 'quotation',
                'entityId' => $q->id,
                'expiresAt' => $q->expiry_date->toISOString(),
            ]);

        // Overdue activities
        $overdueActivities = LeadActivity::where('status', ActivityStatus::SCHEDULED)
            ->where('scheduled_at', '<', now())
            ->with(['lead:id,name', 'user:id,name'])
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'type' => 'activity_overdue',
                'severity' => 'error',
                'title' => __('alerts.activity_overdue'),
                'message' => __('alerts.activity_overdue_message', [
                    'title' => $a->title,
                    'lead' => $a->lead?->name,
                    'user' => $a->user?->name,
                ]),
                'entityType' => 'activity',
                'entityId' => $a->id,
                'scheduledAt' => $a->scheduled_at->toISOString(),
            ]);

        // Meetings needing minutes (completed but no minutes)
        $meetingsNeedingMinutes = LeadMeeting::where('status', MeetingStatus::COMPLETED)
            ->whereNull('minutes')
            ->with('lead:id,name')
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'type' => 'meeting_needs_minutes',
                'severity' => 'info',
                'title' => __('alerts.meeting_needs_minutes'),
                'message' => __('alerts.meeting_needs_minutes_message', [
                    'title' => $m->title,
                    'lead' => $m->lead?->name,
                ]),
                'entityType' => 'meeting',
                'entityId' => $m->id,
            ]);

        // Stale leads (no contact in 7+ days)
        $staleLeadsCount = Lead::whereNotIn('status', [LeadStatus::WON, LeadStatus::LOST])
            ->where(function ($q) {
                $q->whereNull('last_contact_at')
                    ->orWhere('last_contact_at', '<', now()->subDays(7));
            })
            ->count();

        if ($staleLeadsCount > 0) {
            $alerts[] = [
                'type' => 'stale_leads',
                'severity' => 'warning',
                'title' => __('alerts.stale_leads'),
                'message' => __('alerts.stale_leads_message', ['count' => $staleLeadsCount]),
                'count' => $staleLeadsCount,
            ];
        }

        // Hot leads needing attention
        $hotLeadsCount = Lead::where('lead_temperature', 'hot')
            ->whereNotIn('status', [LeadStatus::WON, LeadStatus::LOST])
            ->where(function ($q) {
                $q->whereNull('last_contact_at')
                    ->orWhere('last_contact_at', '<', now()->subDays(2));
            })
            ->count();

        if ($hotLeadsCount > 0) {
            $alerts[] = [
                'type' => 'hot_leads_attention',
                'severity' => 'error',
                'title' => __('alerts.hot_leads_attention'),
                'message' => __('alerts.hot_leads_attention_message', ['count' => $hotLeadsCount]),
                'count' => $hotLeadsCount,
            ];
        }

        return [
            'expiringProposals' => $expiringProposals,
            'expiringQuotations' => $expiringQuotations,
            'overdueActivities' => $overdueActivities,
            'meetingsNeedingMinutes' => $meetingsNeedingMinutes,
            'generalAlerts' => $alerts,
            'summary' => [
                'totalAlerts' => $expiringProposals->count() + $expiringQuotations->count() +
                    $overdueActivities->count() + $meetingsNeedingMinutes->count() + count($alerts),
                'critical' => $overdueActivities->count() + $hotLeadsCount,
                'warnings' => $expiringProposals->count() + $expiringQuotations->count() + $staleLeadsCount,
            ],
        ];
    }

    /**
     * Get dashboard summary for manager
     */
    public function getDashboardSummary(): array
    {
        return [
            'pipeline' => $this->getPipelineHealth(),
            'alerts' => $this->getAlerts(),
            'todaySchedule' => $this->getTodaySchedule(),
        ];
    }

    /**
     * Get today's schedule summary
     */
    private function getTodaySchedule(): array
    {
        $todayActivities = LeadActivity::where('status', ActivityStatus::SCHEDULED)
            ->whereDate('scheduled_at', today())
            ->count();

        $todayMeetings = LeadMeeting::where('status', MeetingStatus::SCHEDULED)
            ->whereDate('scheduled_at', today())
            ->count();

        return [
            'activities' => $todayActivities,
            'meetings' => $todayMeetings,
            'total' => $todayActivities + $todayMeetings,
        ];
    }
}
