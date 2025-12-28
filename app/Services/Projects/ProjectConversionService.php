<?php

namespace App\Services\Projects;

use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Models\Project;
use App\Models\Quotation;
use InvalidArgumentException;
use RuntimeException;

class ProjectConversionService
{
    /**
     * Create a new ProjectConversionService instance.
     *
     * @param ProjectHistoryService $historyService Service for recording project history
     */
    public function __construct(
        private ProjectHistoryService $historyService
    ) {}

    /**
     * Convert an accepted quotation to a project.
     *
     * This method validates that the quotation is in 'accepted' status,
     * ensures no project already exists for this quotation, and creates
     * a new project with the quotation's final_price as budget.
     *
     * @param int $quotationId The ID of the quotation to convert
     * @param array $additionalData Optional additional project data:
     *                              - title: string (optional, defaults to "Project for {company}")
     *                              - description: string (optional)
     *                              - deadline: string|Carbon (optional)
     * @return Project The newly created project
     * @throws InvalidArgumentException If quotation status is not 'accepted'
     * @throws RuntimeException If a project already exists for this quotation
     */
    public function convertQuotation(int $quotationId, array $additionalData = []): Project
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

        // Create project with data from quotation
        $project = Project::create([
            'quotation_id' => $quotation->id,
            'client_id' => $quotation->lead_id, // Lead becomes the client
            'title' => $additionalData['title'] ?? $defaultTitle,
            'description' => $additionalData['description'] ?? null,
            'status' => ProjectStatus::DRAFT,
            'budget' => $quotation->final_price,
            'deadline' => $additionalData['deadline'] ?? null,
        ]);

        // Record initial history entry
        $this->historyService->recordChange(
            $project,
            'status',
            null,
            ProjectStatus::DRAFT->value,
            'Project created from quotation conversion'
        );

        return $project;
    }

    /**
     * Generate a default project title based on the quotation's lead.
     *
     * @param Quotation $quotation The quotation being converted
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
