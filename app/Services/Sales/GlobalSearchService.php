<?php

namespace App\Services\Sales;

use App\Models\Lead;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\Client;
use App\Models\Project;
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
        return Lead::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('company', 'like', "%{$query}%")
                ->orWhere('project_name', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%");
        })
            ->select('id', 'name', 'email', 'company', 'status', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn($lead) => [
                'id' => $lead->id,
                'type' => 'lead',
                'title' => $lead->name,
                'subtitle' => $lead->company,
                'status' => $lead->status?->value,
                'url' => "/dashboard/sales/leads/{$lead->id}",
                'createdAt' => $lead->created_at->toISOString(),
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
                ->orWhereHas('lead', fn($lq) => $lq->where('name', 'like', "%{$query}%"));
        })
            ->with('lead:id,name')
            ->select('id', 'title', 'proposal_number', 'lead_id', 'status', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn($proposal) => [
                'id' => $proposal->id,
                'type' => 'proposal',
                'title' => $proposal->title,
                'subtitle' => $proposal->lead?->name,
                'status' => $proposal->status?->value,
                'url' => "/dashboard/sales/leads/{$proposal->lead_id}?tab=proposals",
                'createdAt' => $proposal->created_at->toISOString(),
            ]);
    }

    /**
     * Search quotations
     */
    private function searchQuotations(string $query, int $limit): Collection
    {
        return Quotation::where(function ($q) use ($query) {
            $q->where('quotation_number', 'like', "%{$query}%")
                ->orWhereHas('lead', fn($lq) => $lq->where('name', 'like', "%{$query}%")
                    ->orWhere('company', 'like', "%{$query}%"));
        })
            ->with('lead:id,name')
            ->select('id', 'quotation_number', 'lead_id', 'final_price', 'currency', 'status', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn($quotation) => [
                'id' => $quotation->id,
                'type' => 'quotation',
                'title' => $quotation->quotation_number ?? "Quotation #{$quotation->id}",
                'subtitle' => $quotation->lead?->name . " - " . number_format($quotation->final_price, 2) . " " . $quotation->currency,
                'status' => $quotation->status?->value,
                'url' => "/dashboard/sales/leads/{$quotation->lead_id}?tab=quotations",
                'createdAt' => $quotation->created_at->toISOString(),
            ]);
    }

    /**
     * Search clients
     */
    private function searchClients(string $query, int $limit): Collection
    {
        return Client::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('company', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%");
        })
            ->select('id', 'name', 'email', 'company', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn($client) => [
                'id' => $client->id,
                'type' => 'client',
                'title' => $client->name,
                'subtitle' => $client->company,
                'status' => null,
                'url' => "/dashboard/clients/{$client->id}",
                'createdAt' => $client->created_at->toISOString(),
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
                ->orWhereHas('client', fn($cq) => $cq->where('name', 'like', "%{$query}%"));
        })
            ->with('client:id,name')
            ->select('id', 'name', 'code', 'client_id', 'status', 'created_at')
            ->limit($limit)
            ->get()
            ->map(fn($project) => [
                'id' => $project->id,
                'type' => 'project',
                'title' => $project->name,
                'subtitle' => $project->client?->name,
                'status' => $project->status?->value,
                'url' => "/dashboard/projects/{$project->id}",
                'createdAt' => $project->created_at->toISOString(),
            ]);
    }
}
