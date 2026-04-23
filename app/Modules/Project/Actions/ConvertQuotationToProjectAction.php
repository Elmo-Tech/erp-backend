<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Modules\Project\Models\Project;
use App\Models\Quotation;
use InvalidArgumentException;
use RuntimeException;

class ConvertQuotationToProjectAction
{
    public function __construct(
        private ProjectHistoryAction $historyAction
    ) {}

    /**
     * Convert an accepted quotation to a project.
     *
     * This method validates that the quotation is in 'accepted' status,
     * ensures no project already exists for this quotation, and creates
     * a new project with the quotation's final_price as budget.
     *
     * @param  int  $quotationId  The ID of the quotation to convert
     * @param  array  $additionalData  Optional additional project data:
     *                                 - title: string (optional, defaults to "Project for {company}")
     *                                 - description: string (optional)
     *                                 - deadline: string|Carbon (optional)
     * @return Project The newly created project
     *
     * @throws InvalidArgumentException If quotation status is not 'accepted'
     * @throws RuntimeException If a project already exists for this quotation
     */
    public function execute(int $quotationId, array $additionalData = []): Project
    {
        // Find the quotation with its lead relationship
        $quotation = Quotation::with('lead')->findOrFail($quotationId);

        // Validate quotation is accepted
        if ($quotation->status !== QuotationStatus::ACCEPTED) {
            throw new InvalidArgumentException(
                'Only accepted quotations can be converted to projects.'
            );
        }

        // Check if project already exists for this quotation
        if (Project::where('quotation_id', $quotationId)->exists()) {
            throw new RuntimeException(
                'A project already exists for this quotation.'
            );
        }

        // Generate default title if not provided
        $defaultTitle = $this->generateDefaultTitle($quotation);
        $clientId = $quotation->lead?->client_id;

        if (! $clientId) {
            throw new RuntimeException(
                'Cannot convert a quotation to a project before the lead has been converted to a client.'
            );
        }

        // Create project with data from quotation
        $project = Project::create([
            'quotation_id' => $quotation->id,
            'client_id' => $clientId,
            'title' => $additionalData['title'] ?? $defaultTitle,
            'description' => $additionalData['description'] ?? null,
            'status' => ProjectStatus::DRAFT,
            'budget' => $quotation->final_price,
            'deadline' => $additionalData['deadline'] ?? null,
        ]);

        // Record initial history entry
        $this->historyAction->recordChange(
            $project,
            'status',
            null,
            ProjectStatus::DRAFT->value,
            'Project created from quotation conversion'
        );

        return $project;
    }

    public function convertQuotation(int $quotationId, array $additionalData = []): Project
    {
        return $this->execute($quotationId, $additionalData);
    }

    /**
     * Generate a default project title based on the quotation's lead.
     *
     * @param  Quotation  $quotation  The quotation being converted
     * @return string The generated title
     */
    private function generateDefaultTitle(Quotation $quotation): string
    {
        $lead = $quotation->lead;

        if ($lead->company) {
            return "Project for {$lead->company}";
        }

        return "Project for {$lead->name}";
    }
}
