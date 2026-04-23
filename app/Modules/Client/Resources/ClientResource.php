<?php

namespace App\Modules\Client\Resources;

use App\Modules\Client\Models\Client;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Client $client */
        $client = $this->resource;

        return [
            'clientId' => $client->id,
            'name' => $client->name ?? '',
            'email' => $client->email ?? '',
            'phone' => $client->phone ?? '',
            'company' => $client->company ?? '',
            'position' => $client->position ?? '',
            'address' => $client->address ?? '',
            'city' => $client->city ?? '',
            'country' => $client->country ?? '',
            'website' => $client->website ?? '',
            'notes' => $client->notes ?? '',
            'status' => $client->status->value,
            'userId' => $client->user_id,
            'leadId' => $client->lead_id,
            'hasPortalAccess' => $client->hasPortalAccess(),
            'totalRevenue' => (float) ($client->total_revenue ?? 0),
            'activeProjectsCount' => (int) ($client->active_projects_count ?? 0),
            'projectsCount' => $this->whenCounted('projects', $client->projects_count ?? 0),
            'projects' => $this->whenLoaded('projects', function () use ($client) {
                return $client->projects->map(fn ($project) => [
                    'projectId' => $project->id,
                    'title' => $project->title,
                    'status' => $project->status->value,
                    'budget' => (float) $project->budget,
                    'deadline' => $project->deadline?->format('Y-m-d'),
                ])->values()->all();
            }),
            'originalLead' => $this->whenLoaded('originalLead', function () use ($client) {
                $source = $client->originalLead->source;

                return [
                    'leadId' => $client->originalLead->id,
                    'name' => $client->originalLead->name ?? '',
                    'source' => $source instanceof BackedEnum ? $source->value : ($source ?? ''),
                ];
            }),
            'createdAt' => $client->created_at?->toISOString() ?? '',
            'updatedAt' => $client->updated_at?->toISOString() ?? '',
        ];
    }
}
