<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Project\Models\Project;
use App\Modules\Lead\Models\Quotation;
use Illuminate\Support\Carbon;
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
     * a new project with the quotation's final_total as budget.
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
        // Find the quotation with its client relationship
        $quotation = Quotation::with('client')->findOrFail($quotationId);

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
        $clientId = $quotation->client_id;

        if (! $clientId) {
            throw new RuntimeException(
                'Cannot convert a quotation to a project without a client.'
            );
        }

        // Create project with data from quotation
        $project = Project::create([
            'quotation_id' => $quotation->id,
            'client_id' => $clientId,
            'title' => $additionalData['title'] ?? $defaultTitle,
            'description' => $additionalData['description'] ?? null,
            'status' => ProjectStatus::DRAFT,
            'budget' => $quotation->final_total,
            'deadline' => $additionalData['deadline'] ?? null,
        ]);

        $this->createInvoicesForPaymentStages($quotation, $project, $clientId);

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
     * Generate a default project title based on the quotation's client.
     *
     * @param  Quotation  $quotation  The quotation being converted
     * @return string The generated title
     */
    private function generateDefaultTitle(Quotation $quotation): string
    {
        $client = $quotation->client;

        if ($client?->company_name) {
            return "Project for {$client->company_name}";
        }

        return 'Project for '.($client?->display_name ?? 'Client');
    }

    private function createInvoicesForPaymentStages(Quotation $quotation, Project $project, int $clientId): void
    {
        $stages = $quotation->payment_stages;

        if (! is_array($stages) || $stages === []) {
            return;
        }

        foreach ($stages as $index => $stage) {
            if (! is_array($stage)) {
                continue;
            }

            $amount = $this->resolveStageAmount($stage, $quotation);
            $stageName = $stage['name'] ?? $stage['stage_name'] ?? 'Stage '.($index + 1);
            $issueDate = now()->toDateString();

            FinanceInvoice::create([
                'client_id' => $clientId,
                'project_id' => $project->id,
                'invoice_number' => FinanceInvoice::generateInvoiceNumber(),
                'issue_date' => $issueDate,
                'due_date' => $this->resolveStageDueDate($stage['due_date'] ?? null, $issueDate),
                'subtotal' => $amount,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'status' => 'draft',
                'notes' => "Auto-created from quotation payment stage: {$stageName}",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function resolveStageAmount(array $stage, Quotation $quotation): float
    {
        if (isset($stage['amount']) && is_numeric($stage['amount'])) {
            return round((float) $stage['amount'], 2);
        }

        if (isset($stage['percentage']) && is_numeric($stage['percentage']) && is_numeric($quotation->final_total)) {
            return round(((float) $quotation->final_total) * ((float) $stage['percentage'] / 100), 2);
        }

        return 0.0;
    }

    private function resolveStageDueDate(mixed $dueDate, string $fallbackDate): string
    {
        if (! is_string($dueDate) || trim($dueDate) === '') {
            return $fallbackDate;
        }

        try {
            return Carbon::parse($dueDate)->toDateString();
        } catch (\Throwable) {
            return $fallbackDate;
        }
    }
}
