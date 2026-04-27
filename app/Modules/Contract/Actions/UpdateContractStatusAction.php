<?php

namespace App\Modules\Contract\Actions;

use App\Enums\ContractStatus;
use App\Enums\MilestoneStatus;
use App\Modules\Contract\Models\Contract;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\Project\Actions\ConvertQuotationToProjectAction;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\User\Models\User;

class UpdateContractStatusAction
{
    public function __construct(
        private ConvertQuotationToProjectAction $projectConversionService
    ) {}

    public function execute(Contract $contract, string $status, ?User $user = null): Contract
    {
        $statusEnum = ContractStatus::from($status);

        if ($statusEnum === ContractStatus::ACTIVE) {
            $this->activateProject($contract, $user);
        }

        $contract->update([
            'status' => $statusEnum,
            'updated_by' => $user?->id ?? auth()->id(),
        ]);

        return $contract->fresh()->load(['milestones.invoice', 'quotation.lead', 'project', 'template']);
    }

    public function activate(Contract $contract, ?User $user = null): Contract
    {
        if (! in_array($contract->status, [ContractStatus::SIGNED, ContractStatus::ACTIVE], true)) {
            throw new \InvalidArgumentException('Only signed contracts can be activated');
        }

        $this->activateProject($contract, $user);

        $contract->update([
            'status' => ContractStatus::ACTIVE,
            'updated_by' => $user?->id ?? auth()->id(),
        ]);

        return $contract->fresh()->load(['milestones.invoice', 'quotation.lead', 'project', 'template']);
    }

    private function activateProject(Contract $contract, ?User $user = null): void
    {
        $actorId = $user?->id ?? auth()->id();
        $contract->loadMissing('quotation.lead', 'project');
        $quotation = $contract->quotation;

        if ($contract->project_id) {
            $project = $contract->project;
            if ($project) {
                $project->update([
                    'status' => ProjectStatus::IN_PROGRESS,
                    'updated_by' => $actorId,
                ]);
            }

            return;
        }

        if (! $quotation) {
            return;
        }

        $project = $this->projectConversionService->convertQuotation($quotation->id, [
            'title' => "Project for {$quotation->lead->name}",
            'description' => 'Project created from contract activation',
        ]);

        $project->update([
            'status' => ProjectStatus::IN_PROGRESS,
            'updated_by' => $actorId,
        ]);

        $contract->update(['project_id' => $project->id]);
    }
}
