<?php

namespace App\Modules\Sales\Services;

use App\Modules\Proposal\Models\ProposalTemplate;
use App\Modules\Contract\Models\ContractTemplate;
use App\Modules\Template\Actions\GetTemplatesAction;
use App\Modules\Template\Actions\CreateProposalTemplateAction;
use App\Modules\Template\Actions\CreateContractTemplateAction;
use App\Modules\Template\Actions\UpdateProposalTemplateAction;
use App\Modules\Template\Actions\UpdateContractTemplateAction;
use App\Modules\Template\Actions\RenderTemplateAction;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TemplateService
{
    public function __construct(
        private GetTemplatesAction $getTemplatesAction,
        private CreateProposalTemplateAction $createProposalTemplateAction,
        private CreateContractTemplateAction $createContractTemplateAction,
        private UpdateProposalTemplateAction $updateProposalTemplateAction,
        private UpdateContractTemplateAction $updateContractTemplateAction,
        private RenderTemplateAction $renderTemplateAction
    ) {}

    public function getProposalTemplates(?string $type = null): Collection
    {
        return $this->getTemplatesAction->getProposalTemplates($type);
    }

    public function getContractTemplates(?string $type = null): Collection
    {
        return $this->getTemplatesAction->getContractTemplates($type);
    }

    public function createProposalTemplate(array $data, User $user): ProposalTemplate
    {
        return $this->createProposalTemplateAction->execute($data, $user);
    }

    public function createContractTemplate(array $data, User $user): ContractTemplate
    {
        return $this->createContractTemplateAction->execute($data, $user);
    }

    public function generateProposalContent(ProposalTemplate $template, Quotation $quotation, array $customValues = []): string
    {
        return $this->renderTemplateAction->renderProposal($template, $quotation, $customValues);
    }

    public function generateContractContent(
        ContractTemplate $template,
        array $partyOne,
        array $partyTwo,
        float $totalAmount,
        string $currency,
        array $customValues = []
    ): string {
        return $this->renderTemplateAction->renderContract(
            $template,
            $partyOne,
            $partyTwo,
            $totalAmount,
            $currency,
            $customValues
        );
    }

    public function updateProposalTemplate(ProposalTemplate $template, array $data, User $user): ProposalTemplate
    {
        return $this->updateProposalTemplateAction->execute($template, $data, $user);
    }

    public function updateContractTemplate(ContractTemplate $template, array $data, User $user): ContractTemplate
    {
        return $this->updateContractTemplateAction->execute($template, $data, $user);
    }
}
