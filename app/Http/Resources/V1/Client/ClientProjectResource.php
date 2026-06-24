<?php

namespace App\Http\Resources\V1\Client;

use App\Http\Resources\V1\Project\ProjectFileResource;
use App\Models\Project;
use App\Services\Client\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ClientProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * CRITICAL: This resource hides ALL internal data from clients.
     *
     * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;

        $progressService = app(ProgressService::class);

        return [
            'projectId' => $project->id,
            'title' => $project->title,
            'status' => $project->status->value,
            'statusLabel' => $project->status->label(),
            'progressPercentage' => $progressService->calculateProgress($project),
            'deadline' => $project->deadline?->toDateString() ?? '',
            'files' => $this->whenLoaded('files', function () use ($request) {
                return ProjectFileResource::collection(
                    $this->files->filter(fn ($file) => $file->canUserView($request->user()))->values()
                );
            }),
            'createdAt' => $project->created_at?->toISOString() ?? '',
            // HIDDEN: budget, invoices, payments, UI approval workflow, tasks,
            // developers, wikis, timeEntries, internalNotes, quotations.
        ];
    }
}
