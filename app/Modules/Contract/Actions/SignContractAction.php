<?php

namespace App\Modules\Contract\Actions;

use App\Enums\ContractStatus;
use App\Enums\MilestoneStatus;
use App\Modules\Contract\Models\Contract;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\Project\Actions\ConvertQuotationToProjectAction;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\User\Models\User;
use App\Modules\Quotation\Models\Quotation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SignContractAction
{
    public function __construct(
        private ConvertQuotationToProjectAction $projectConversionService
    ) {}

    public function execute(Contract $contract, array $data = [], ?User $user = null): Contract
    {
        return DB::transaction(function () use ($contract, $data, $user) {
            $actorId = $user?->id ?? auth()->id();
            $actor = $user ?? auth()->user();

            if (! $contract->canBeSigned()) {
                throw new \InvalidArgumentException('Contract cannot be signed in current status');
            }

            $updateData = [
                'status' => ContractStatus::SIGNED,
                'signed_at' => now(),
                'signed_by_client' => $data['signedByClient'] ?? $contract->party_two['name'] ?? 'Client',
                'updated_by' => $actorId,
            ];

            if (isset($data['signedPdf']) && $data['signedPdf'] instanceof UploadedFile) {
                $data['signedPdf']->store('contracts/signed', 'private');
            }

            $contract->update($updateData);
            $contract->loadMissing('quotation.client', 'project');

            $this->activateProject($contract, $user);

            return $contract->fresh()->load(['milestones.invoice', 'quotation.client', 'project', 'template']);
        });
    }

    public function signContract(Contract $contract, string $signedByClient, User $user): Contract
    {
        return $this->execute($contract, ['signedByClient' => $signedByClient], $user);
    }

    private function activateProject(Contract $contract, ?User $user = null): void
    {
        $actorId = $user?->id ?? auth()->id();
        $contract->loadMissing('quotation.client', 'project');
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
            'title' => 'Project for '.($quotation->client?->display_name ?? 'Client'),
            'description' => 'Project created from signed contract',
        ]);

        $project->update([
            'status' => ProjectStatus::IN_PROGRESS,
            'updated_by' => $actorId,
        ]);

        $contract->update(['project_id' => $project->id]);
    }
}
