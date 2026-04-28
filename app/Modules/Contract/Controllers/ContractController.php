<?php

namespace App\Modules\Contract\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Contract\Actions\CreateContractAction;
use App\Modules\Contract\Actions\GetDueMilestonesAction;
use App\Modules\Contract\Actions\MarkMilestonePaidAction;
use App\Modules\Contract\Actions\SignContractAction;
use App\Modules\Contract\Actions\UpdateContractAction;
use App\Modules\Contract\Actions\UpdateContractStatusAction;
use App\Modules\Contract\Models\Contract;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\Contract\Requests\SignContractRequest;
use App\Modules\Contract\Requests\StoreContractRequest;
use App\Modules\Contract\Requests\UpdateContractRequest;
use App\Modules\Contract\Resources\ContractCollection;
use App\Modules\Contract\Resources\ContractMilestoneResource;
use App\Modules\Contract\Resources\ContractResource;
use App\Models\Quotation;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContractController extends Controller
{
    public function __construct(
        private CreateContractAction $createContractAction,
        private UpdateContractAction $updateContractAction,
        private SignContractAction $signContractAction,
        private UpdateContractStatusAction $updateContractStatusAction,
        private GetDueMilestonesAction $getDueMilestonesAction,
        private MarkMilestonePaidAction $markMilestonePaidAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Request $request): JsonResponse
    {
        $contracts = QueryBuilder::for(Contract::class)
            ->with(['quotation.lead', 'project', 'template', 'milestones.invoice'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::callback('projectId', fn ($query, $value) => $query->where('project_id', $value)),
                AllowedFilter::callback('quotationId', fn ($query, $value) => $query->where('quotation_id', $value)),
                AllowedFilter::callback('leadId', function ($query, $value) {
                    $query->whereHas('quotation', fn ($quotationQuery) => $quotationQuery->where('lead_id', $value));
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($contractQuery) use ($value) {
                        $contractQuery->where('title', 'like', "%{$value}%")
                            ->orWhere('contract_number', 'like', "%{$value}%")
                            ->orWhere('file_number', 'like', "%{$value}%")
                            ->orWhereHas('quotation', function ($quotationQuery) use ($value) {
                                $quotationQuery->where('quotation_number', 'like', "%{$value}%")
                                    ->orWhereHas('lead', function ($leadQuery) use ($value) {
                                        $leadQuery->where('name', 'like', "%{$value}%")
                                            ->orWhere('company', 'like', "%{$value}%");
                                    });
                            });
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'signed_at', 'status', 'start_date', 'end_date'])
            ->defaultSort('-created_at')
            ->paginate($request->input('perPage', 15));

        return ApiResponse::success(
            new ContractCollection($contracts),
            __('contracts.retrieved')
        );
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $contract = $this->createContractAction->execute(
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.created'),
            201
        );
    }

    public function createFromQuotation(StoreContractRequest $request, Quotation $quotation): JsonResponse
    {
        $contract = $this->createContractAction->createFromQuotation(
            $quotation,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.created'),
            201
        );
    }

    public function show(Contract $contract): JsonResponse
    {
        $contract->load(['milestones.invoice', 'quotation', 'project', 'template']);

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.retrieved')
        );
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        $contract = $this->updateContractAction->execute(
            $contract,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.updated')
        );
    }

    public function sign(SignContractRequest $request, Contract $contract): JsonResponse
    {
        $contract = $this->signContractAction->signContract(
            $contract,
            $request->validated()['signedByClient'],
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.signed')
        );
    }

    public function signPatch(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'signedByClient' => 'nullable|string|max:255',
            'signedPdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $contract = $this->signContractAction->execute(
            $contract,
            $validated,
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.signed_and_lead_converted')
        );
    }

    public function activate(Contract $contract): JsonResponse
    {
        $contract = $this->updateContractStatusAction->activate(
            $contract,
            request()->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.activated')
        );
    }

    public function updateStatus(Request $request, Contract $contract): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:draft,pending_signature,signed,active,completed,terminated',
        ]);

        $contract = $this->updateContractStatusAction->execute(
            $contract,
            $request->input('status'),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.status_updated')
        );
    }

    public function dueMilestones(): JsonResponse
    {
        $milestones = $this->getDueMilestonesAction->execute();

        return ApiResponse::success([
            'milestones' => ContractMilestoneResource::collection($milestones),
        ], __('contracts.milestones_retrieved'));
    }

    public function markMilestonePaid(Request $request, ContractMilestone $milestone): JsonResponse
    {
        $milestone = $this->markMilestonePaidAction->execute(
            $milestone,
            $request->input('invoiceId')
        );

        return ApiResponse::success(
            new ContractMilestoneResource($milestone),
            __('contracts.milestone_paid')
        );
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $contract->delete();

        return ApiResponse::success(null, __('contracts.deleted'));
    }
}
