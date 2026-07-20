<?php

namespace App\Modules\Client\Resources;

use App\Modules\Client\Actions\CalculateProjectProgressAction;
use App\Modules\Project\Models\Project;
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

        $calculateProjectProgress = app(CalculateProjectProgressAction::class);

        return [
            'projectId' => $project->id,
            'title' => $project->title,
            'status' => $project->status->value,
            'statusLabel' => $project->status->label(),
            'progressPercentage' => $calculateProjectProgress->execute($project),
            'deadline' => $project->deadline?->toDateString() ?? '',
            'budget' => (float) ($project->budget ?? 0.0),
            'currency' => 'USD', // Default currency as per requirements
            'uiApprovedAt' => $project->ui_approved_at?->toISOString() ?? '',
            'canApproveUI' => $project->status->value === 'ui_ux' && ! $project->ui_approved_at,
            'createdAt' => $project->created_at?->toISOString() ?? '',
            // Invoices for this project (using ClientInvoiceResource)
            'invoices' => $this->whenLoaded('invoices', function () use ($project) {
                return ClientInvoiceResource::collection($project->invoices);
            }),
            // HIDDEN: description, tasks, developers, wikis, timeEntries, internalNotes
        ];
    }
}
