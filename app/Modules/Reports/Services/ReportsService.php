<?php

namespace App\Modules\Reports\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Lead\Models\Lead;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportsService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(Request $request): array
    {
        $projects = $this->projectQuery($request)->get();
        $invoices = $this->invoiceQuery($request)->get();
        $entries = $this->timeEntryQuery($request)->with('user:id,name')->get();
        $subscriptions = $this->subscriptionQuery($request)->get();
        $leads = $this->leadQuery($request)->get();

        $paidInvoices = $invoices->where('status', 'paid');
        $loggedMinutes = (int) $entries->sum('duration_minutes');
        $internalCost = $this->internalCost($entries);
        $canViewCost = $request->user()?->can('view_cost') || $request->user()?->can('view_internal_costs');
        $canViewProfit = $request->user()?->can('view_profitability');

        return [
            'filters' => $this->filterSummary($request),
            'metrics' => [
                'projectsTotal' => $projects->count(),
                'activeProjects' => $projects->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'completedProjects' => $projects->where('status', 'completed')->count(),
                'invoicedTotal' => (float) $invoices->sum('total_amount'),
                'paidTotal' => (float) $paidInvoices->sum('total_amount'),
                'overdueTotal' => (float) $invoices->where('status', 'overdue')->sum('total_amount'),
                'subscriptionCostTotal' => (float) $subscriptions->sum('cost'),
                'openLeads' => $leads->where('status', 'active')->count(),
                'loggedMinutes' => $loggedMinutes,
                'internalCost' => $canViewCost ? $internalCost : null,
                'profit' => $canViewProfit ? round((float) $paidInvoices->sum('total_amount') - $internalCost, 2) : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function projects(Request $request): array
    {
        $projects = $this->projectQuery($request)
            ->with(['client:id,display_name,company_name'])
            ->orderByDesc('created_at')
            ->get();

        $rows = $projects->map(function (Project $project) use ($request): array {
            $invoices = $this->invoiceQuery($request)
                ->where('project_id', $project->id)
                ->get();

            $entries = $this->timeEntryQuery($request)
                ->whereHas('task', fn (Builder $query) => $query->where('project_id', $project->id))
                ->with('user:id,name,internal_hourly_cost')
                ->get();

            $internalCost = $this->internalCost($entries);
            $paidTotal = (float) $invoices->where('status', 'paid')->sum('total_amount');
            $canViewCost = $request->user()?->can('view_cost') || $request->user()?->can('view_internal_costs');
            $canViewProfit = $request->user()?->can('view_profitability');
            $profit = round($paidTotal - $internalCost, 2);

            return [
                'projectId' => $project->id,
                'title' => $project->title,
                'clientId' => $project->client_id,
                'clientName' => $project->client?->display_name ?? $project->client?->company_name,
                'status' => $project->status?->value ?? (string) $project->status,
                'budget' => (float) ($project->budget ?? 0),
                'deadline' => $project->deadline?->toDateString(),
                'invoicedTotal' => (float) $invoices->sum('total_amount'),
                'paidTotal' => $paidTotal,
                'loggedMinutes' => (int) $entries->sum('duration_minutes'),
                'internalCost' => $canViewCost ? $internalCost : null,
                'profit' => $canViewProfit ? $profit : null,
                'marginPercent' => ($canViewProfit && $paidTotal > 0) ? round(($profit / $paidTotal) * 100, 2) : null,
            ];
        })->values();

        return [
            'filters' => $this->filterSummary($request),
            'summary' => [
                'projects' => $rows->count(),
                'invoicedTotal' => (float) $rows->sum('invoicedTotal'),
                'paidTotal' => (float) $rows->sum('paidTotal'),
                'loggedMinutes' => (int) $rows->sum('loggedMinutes'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function teamTime(Request $request): array
    {
        $entries = $this->timeEntryQuery($request)
            ->with(['user:id,name,email,internal_hourly_cost', 'task.project:id,title'])
            ->get();

        $tasks = $this->taskQuery($request)->get();
        $canViewCost = $request->user()?->can('view_cost') || $request->user()?->can('view_internal_costs');

        $rows = $entries
            ->groupBy('user_id')
            ->map(function (Collection $userEntries, int $userId) use ($tasks, $canViewCost): array {
                $user = $userEntries->first()?->user;
                $userTasks = $tasks->where('assignee_id', $userId);
                $loggedMinutes = (int) $userEntries->sum('duration_minutes');
                $internalCost = $this->internalCost($userEntries);

                return [
                    'userId' => $userId,
                    'name' => $user?->name ?? 'Unknown user',
                    'email' => $user?->email,
                    'assignedTasks' => $userTasks->count(),
                    'completedTasks' => $userTasks->where('status', ExecutionTaskStatus::DONE)->count(),
                    'loggedMinutes' => $loggedMinutes,
                    'internalCost' => $canViewCost ? $internalCost : null,
                ];
            })
            ->sortByDesc('loggedMinutes')
            ->values();

        return [
            'filters' => $this->filterSummary($request),
            'summary' => [
                'members' => $rows->count(),
                'loggedMinutes' => (int) $rows->sum('loggedMinutes'),
                'assignedTasks' => (int) $rows->sum('assignedTasks'),
                'completedTasks' => (int) $rows->sum('completedTasks'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function salesFinance(Request $request): array
    {
        $invoices = $this->invoiceQuery($request)->with(['client:id,display_name,company_name', 'project:id,title'])->get();
        $subscriptions = $this->subscriptionQuery($request)->with(['client:id,display_name,company_name', 'project:id,title'])->get();
        $leads = $this->leadQuery($request)->get();

        $invoiceRows = $invoices->map(fn (FinanceInvoice $invoice): array => [
            'type' => 'invoice',
            'id' => $invoice->id,
            'reference' => $invoice->invoice_number,
            'clientId' => $invoice->client_id,
            'clientName' => $invoice->client?->display_name ?? $invoice->client?->company_name,
            'projectId' => $invoice->project_id,
            'projectTitle' => $invoice->project?->title,
            'status' => $invoice->status,
            'amount' => (float) $invoice->total_amount,
            'dueDate' => $invoice->due_date?->toDateString(),
        ]);

        $subscriptionRows = $subscriptions->map(fn (Subscription $subscription): array => [
            'type' => 'subscription',
            'id' => $subscription->id,
            'reference' => $subscription->name,
            'clientId' => $subscription->client_id,
            'clientName' => $subscription->client?->display_name ?? $subscription->client?->company_name,
            'projectId' => $subscription->project_id,
            'projectTitle' => $subscription->project?->title,
            'status' => $subscription->status,
            'amount' => (float) $subscription->cost,
            'dueDate' => $subscription->next_billing_date?->toDateString(),
        ]);

        return [
            'filters' => $this->filterSummary($request),
            'summary' => [
                'invoices' => $invoices->count(),
                'paidTotal' => (float) $invoices->where('status', 'paid')->sum('total_amount'),
                'overdueTotal' => (float) $invoices->where('status', 'overdue')->sum('total_amount'),
                'subscriptionCostTotal' => (float) $subscriptions->sum('cost'),
                'leads' => $leads->count(),
            ],
            'rows' => $invoiceRows->merge($subscriptionRows)->values(),
            'leadStages' => $leads->groupBy(fn (Lead $lead) => $lead->stage?->value ?? (string) $lead->stage)
                ->map(fn (Collection $items) => $items->count())
                ->toArray(),
        ];
    }

    /**
     * @return array{0:string, 1:array<int, string>, 2:array<int, array<int, mixed>>}
     */
    public function csv(Request $request, string $report): array
    {
        return match ($report) {
            'projects' => $this->projectsCsv($request),
            'team-time' => $this->teamTimeCsv($request),
            'sales-finance' => $this->salesFinanceCsv($request),
            default => $this->overviewCsv($request),
        };
    }

    private function overviewCsv(Request $request): array
    {
        $metrics = $this->overview($request)['metrics'];
        $rows = collect($metrics)->map(fn ($value, string $key): array => [$key, $value])->values()->all();

        return ['overview-report.csv', ['Metric', 'Value'], $rows];
    }

    private function projectsCsv(Request $request): array
    {
        $rows = collect($this->projects($request)['rows'])
            ->map(fn (array $row): array => [
                $row['projectId'],
                $row['title'],
                $row['clientName'],
                $row['status'],
                $row['budget'],
                $row['invoicedTotal'],
                $row['paidTotal'],
                $row['loggedMinutes'],
                $row['internalCost'],
                $row['profit'],
                $row['marginPercent'],
            ])
            ->all();

        return ['projects-report.csv', ['Project ID', 'Title', 'Client', 'Status', 'Budget', 'Invoiced', 'Paid', 'Logged Minutes', 'Internal Cost', 'Profit', 'Margin Percent'], $rows];
    }

    private function teamTimeCsv(Request $request): array
    {
        $rows = collect($this->teamTime($request)['rows'])
            ->map(fn (array $row): array => [
                $row['userId'],
                $row['name'],
                $row['email'],
                $row['assignedTasks'],
                $row['completedTasks'],
                $row['loggedMinutes'],
                $row['internalCost'],
            ])
            ->all();

        return ['team-time-report.csv', ['User ID', 'Name', 'Email', 'Assigned Tasks', 'Completed Tasks', 'Logged Minutes', 'Internal Cost'], $rows];
    }

    private function salesFinanceCsv(Request $request): array
    {
        $rows = collect($this->salesFinance($request)['rows'])
            ->map(fn (array $row): array => [
                $row['type'],
                $row['id'],
                $row['reference'],
                $row['clientName'],
                $row['projectTitle'],
                $row['status'],
                $row['amount'],
                $row['dueDate'],
            ])
            ->all();

        return ['sales-finance-report.csv', ['Type', 'ID', 'Reference', 'Client', 'Project', 'Status', 'Amount', 'Due Date'], $rows];
    }

    /**
     * @return Builder<Project>
     */
    private function projectQuery(Request $request): Builder
    {
        $query = Project::query();

        if ($request->filled('projectId')) {
            $query->where('id', $request->integer('projectId'));
        }

        if ($request->filled('clientId')) {
            $query->where('client_id', $request->integer('clientId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $query;
    }

    /**
     * @return Builder<FinanceInvoice>
     */
    private function invoiceQuery(Request $request): Builder
    {
        $query = FinanceInvoice::query();

        if ($request->filled('projectId')) {
            $query->where('project_id', $request->integer('projectId'));
        }

        if ($request->filled('clientId')) {
            $query->where('client_id', $request->integer('clientId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('due_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('due_date', '<=', $request->query('to'));
        }

        return $query;
    }

    /**
     * @return Builder<ProjectTimeEntry>
     */
    private function timeEntryQuery(Request $request): Builder
    {
        $query = ProjectTimeEntry::query()->whereNotNull('duration_minutes');

        if ($request->filled('userId')) {
            $query->where('user_id', $request->integer('userId'));
        }

        if ($request->filled('projectId')) {
            $query->whereHas('task', fn (Builder $taskQuery) => $taskQuery->where('project_id', $request->integer('projectId')));
        }

        if ($request->filled('status')) {
            $query->whereHas('task', fn (Builder $taskQuery) => $taskQuery->where('status', $request->query('status')));
        }

        if ($request->filled('from')) {
            $query->whereDate('start_time', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('start_time', '<=', $request->query('to'));
        }

        return $query;
    }

    /**
     * @return Builder<ProjectTask>
     */
    private function taskQuery(Request $request): Builder
    {
        $query = ProjectTask::query();

        if ($request->filled('userId')) {
            $query->where('assignee_id', $request->integer('userId'));
        }

        if ($request->filled('projectId')) {
            $query->where('project_id', $request->integer('projectId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('due_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('due_date', '<=', $request->query('to'));
        }

        return $query;
    }

    /**
     * @return Builder<Subscription>
     */
    private function subscriptionQuery(Request $request): Builder
    {
        $query = Subscription::query();

        if ($request->filled('projectId')) {
            $query->where('project_id', $request->integer('projectId'));
        }

        if ($request->filled('clientId')) {
            $query->where('client_id', $request->integer('clientId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('next_billing_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('next_billing_date', '<=', $request->query('to'));
        }

        return $query;
    }

    /**
     * @return Builder<Lead>
     */
    private function leadQuery(Request $request): Builder
    {
        $query = Lead::query();

        if ($request->filled('userId')) {
            $query->where('assigned_to', $request->integer('userId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        return $query;
    }

    /**
     * @param  Collection<int, ProjectTimeEntry>  $entries
     */
    private function internalCost(Collection $entries): float
    {
        return round($entries->sum(function (ProjectTimeEntry $entry): float {
            $cost = (float) ($entry->user?->internal_hourly_cost ?? 0);

            return ((int) ($entry->duration_minutes ?? 0) / 60) * $cost;
        }), 2);
    }

    /**
     * @return array<string, string|null>
     */
    private function filterSummary(Request $request): array
    {
        return [
            'from' => $this->dateString($request->query('from')),
            'to' => $this->dateString($request->query('to')),
            'projectId' => $request->query('projectId'),
            'clientId' => $request->query('clientId'),
            'userId' => $request->query('userId'),
            'status' => $request->query('status'),
        ];
    }

    private function dateString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
