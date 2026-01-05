<?php

namespace App\Http\Resources\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'clientId' => $this->id,
            'name' => $this->name ?? '',
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
            'company' => $this->company ?? '',
            'position' => $this->position ?? '',
            'address' => $this->address ?? '',
            'city' => $this->city ?? '',
            'country' => $this->country ?? '',
            'website' => $this->website ?? '',
            'notes' => $this->notes ?? '',
            'status' => $this->status?->value ?? $this->status ?? 'active',
            'userId' => $this->user_id,
            'leadId' => $this->lead_id,
            'hasPortalAccess' => $this->hasPortalAccess(),
            'totalRevenue' => (float) ($this->total_revenue ?? 0),
            'activeProjectsCount' => (int) ($this->active_projects_count ?? 0),
            'projectsCount' => $this->whenCounted('projects', $this->projects_count ?? 0),
            'projects' => $this->whenLoaded('projects', function () {
                return $this->projects->map(fn ($project) => [
                    'projectId' => $project->id,
                    'title' => $project->title,
                    'status' => $project->status->value ?? $project->status,
                    'budget' => (float) $project->budget,
                    'deadline' => $project->deadline?->format('Y-m-d'),
                ]);
            }),
            'originalLead' => $this->whenLoaded('originalLead', function () {
                return [
                    'leadId' => $this->originalLead->id,
                    'name' => $this->originalLead->name ?? '',
                    'source' => $this->originalLead->source?->value ?? '',
                ];
            }),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}
