<?php

namespace App\Services\Projects;

use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Models\LookupOption;
use App\Models\Project;
use App\Models\Quotation;
use InvalidArgumentException;
use RuntimeException;

class ProjectConversionService
{
    /**
     * Create a new ProjectConversionService instance.
     *
     * @param  ProjectHistoryService  $historyService  Service for recording project history
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
     * @param  int  $quotationId  The ID of the quotation to convert
     * @param  array  $additionalData  Optional additional project data:
     *                                 - title: string (optional, defaults to "Project for {company}")
     *                                 - description: string (optional)
     *                                 - deadline: string|Carbon (optional)
     *                                 - project_type_id: int|null (optional)
     * @return Project The newly created project
     *
     * @throws InvalidArgumentException If quotation status is not 'accepted'
     * @throws RuntimeException If a project already exists for this quotation
     */
    public function convertQuotation(int $quotationId, array $additionalData = []): Project
    {
        $projectTypeId = $additionalData['project_type_id'] ?? null;

        if ($projectTypeId !== null && ! LookupOption::ofType('project_type')
            ->active()
            ->whereKey($projectTypeId)
            ->exists()) {
            throw new InvalidArgumentException('The selected project type must be active.');
        }

        // Find the quotation with its lead relationship
        $quotation = Quotation::with('lead')->findOrFail($quotationId);

        // Validate quotation is accepted
        if ($quotation->status !== QuotationStatus::APPROVED) {
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
            'project_type_id' => $projectTypeId,
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
