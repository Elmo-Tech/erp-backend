<?php

namespace App\Modules\Sales\Services;

use App\Modules\CRM\Models\Client;
use App\Modules\Project\Models\Project;
use App\Modules\Proposal\Models\Proposal;
use App\Modules\Quotation\Models\Quotation;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    /**
     * Search across all sales entities
     */
    public function search(string $query, array $types = [], int $limit = 20): array
    {
        $results = [];
        $searchTypes = empty($types) ? ['leads', 'proposals', 'quotations', 'clients', 'projects'] : $types;

        if (in_array('leads', $searchTypes)) {
            $results['leads'] = $this->searchLeads($query, $limit);
        }

        if (in_array('proposals', $searchTypes)) {
            $results['proposals'] = $this->searchProposals($query, $limit);
        }

        if (in_array('quotations', $searchTypes)) {
            $results['quotations'] = $this->searchQuotations($query, $limit);
        }

        if (in_array('clients', $searchTypes)) {
            $results['clients'] = $this->searchClients($query, $limit);
        }

        if (in_array('projects', $searchTypes)) {
            $results['projects'] = $this->searchProjects($query, $limit);
        }

        return [
            'results' => $results,
            'totalCount' => collect($results)->flatten(1)->count(),
            'query' => $query,
        ];
    }

    /**
     * Search leads
     */
    private function searchLeads(string $query, int $limit): Collection
    {
        return Client::leads()
            ->where(function ($q) use ($query) {
                $q->where('display_name', 'like', "%{$query}%")
                    ->orWhere('primary_email', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%")
                    ->orWhere('primary_phone', 'like', "%{$query}%");
        })
            ->select('id', 'display_name', 'company_name', 'status', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($lead) => [
                'id' => $lead->id,
                'type' => 'lead',
                'title' => $lead->display_name ?? '',
                'subtitle' => $lead->company_name ?? '',
                'status' => $lead->status ?? null,
                'url' => "/dashboard/sales/leads/{$lead->id}",
                'createdAt' => $lead->created_at?->toISOString() ?? '',
            ]);
    }

    /**
     * Search proposals
     */
    private function searchProposals(string $query, int $limit): Collection
    {
        return Proposal::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('proposal_number', 'like', "%{$query}%")
                ->orWhereHas('client', fn ($cq) => $cq->where('display_name', 'like', "%{$query}%"));
        })
            ->with('client:id,display_name')
            ->select('id', 'title', 'proposal_number', 'client_id', 'status', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($proposal) => [
                'id' => $proposal->id,
                'type' => 'proposal',
                'title' => $proposal->title ?? '',
                'subtitle' => $proposal->client?->name ?? '',
                'status' => $proposal->status?->value ?? null,
                'url' => "/dashboard/sales/leads/{$proposal->client_id}?tab=proposals",
                'createdAt' => $proposal->created_at?->toISOString() ?? '',
            ]);
    }

    /**
     * Search quotations
     */
    private function searchQuotations(string $query, int $limit): Collection
    {
        return Quotation::where(function ($q) use ($query) {
            $q->where('quotation_number', 'like', "%{$query}%")
                ->orWhereHas('client', fn ($cq) => $cq->where('display_name', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%"));
        })
            ->with('client:id,display_name')
            ->select('id', 'quotation_number', 'client_id', 'final_price', 'currency', 'status', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($quotation) => [
                'id' => $quotation->id,
                'type' => 'quotation',
                'title' => $quotation->quotation_number ?? "Quotation #{$quotation->id}",
                'subtitle' => ($quotation->client?->name ?? '').' - '.number_format($quotation->final_price ?? 0, 2).' '.($quotation->currency ?? ''),
                'status' => $quotation->status?->value ?? null,
                'url' => "/dashboard/sales/leads/{$quotation->client_id}?tab=quotations",
                'createdAt' => $quotation->created_at?->toISOString() ?? '',
            ]);
    }

    /**
     * Search clients
     */
    private function searchClients(string $query, int $limit): Collection
    {
        return Client::where(function ($q) use ($query) {
            $q->where('display_name', 'like', "%{$query}%")
                ->orWhere('primary_email', 'like', "%{$query}%")
                ->orWhere('company_name', 'like', "%{$query}%")
                ->orWhere('primary_phone', 'like', "%{$query}%");
        })
            ->select('id', 'display_name', 'company_name', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($client) => [
                'id' => $client->id,
                'type' => 'client',
                'title' => $client->display_name ?? '',
                'subtitle' => $client->company_name ?? '',
                'status' => null,
                'url' => "/dashboard/clients/{$client->id}",
                'createdAt' => $client->created_at?->toISOString() ?? '',
            ]);
    }

    /**
     * Search projects
     */
    private function searchProjects(string $query, int $limit): Collection
    {
        return Project::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "%{$query}%")
                ->orWhereHas('client', fn ($cq) => $cq->where('display_name', 'like', "%{$query}%"));
        })
            ->with('client:id,display_name')
            ->select('id', 'name', 'code', 'client_id', 'status', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'type' => 'project',
                'title' => $project->name ?? '',
                'subtitle' => $project->client?->name ?? '',
                'status' => $project->status?->value ?? null,
                'url' => "/dashboard/projects/{$project->id}",
                'createdAt' => $project->created_at?->toISOString() ?? '',
            ]);
    }
}
