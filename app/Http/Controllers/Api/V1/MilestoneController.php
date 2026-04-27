<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Milestones\CreateMilestoneInvoiceRequest;
use App\Http\Requests\V1\Milestones\LinkMilestoneInvoiceRequest;
use App\Modules\Invoice\Resources\Invoices\InvoiceResource;
use App\Http\Resources\V1\Milestones\MilestoneResource;
use App\Models\ContractMilestone;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Client\Actions\CreateMilestoneInvoiceAction;
use App\Modules\Client\Actions\LinkInvoiceToMilestoneAction;
use App\Modules\Client\Actions\MarkInvoiceAsPaidAction;
use App\Modules\Client\Actions\SendInvoiceAction;
use App\Services\Sales\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class MilestoneController extends Controller
{
    public function __construct(
        private CreateMilestoneInvoiceAction $createMilestoneInvoiceAction,
        private LinkInvoiceToMilestoneAction $linkInvoiceToMilestoneAction,
        private MarkInvoiceAsPaidAction $markInvoiceAsPaidAction,
        private SendInvoiceAction $sendInvoiceAction,
        private ContractService $contractService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/milestones/{id}/create-invoice",
     *     operationId="createMilestoneInvoice",
     *     tags={"Milestones"},
     *     summary="Create invoice from milestone",
     *     description="Creates a new invoice linked to the specified milestone. The invoice amount and currency are taken from the milestone/contract.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Milestone ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="dueDate", type="string", format="date", example="2025-02-15", description="Invoice due date (defaults to milestone due date or 14 days from now)"),
     *             @OA\Property(property="notes", type="string", example="Payment for design phase", description="Additional notes for the invoice"),
     *             @OA\Property(property="sendImmediately", type="boolean", example=false, description="Whether to send the invoice immediately after creation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Invoice created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice created from milestone successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="invoice", ref="#/components/schemas/InvoiceResource"),
     *                 @OA\Property(property="milestone", ref="#/components/schemas/MilestoneResource")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Milestone already has invoice or is paid",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Milestone already has an invoice linked")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Milestone not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Milestone not found")
     *         )
     *     )
     * )
     */
    public function createInvoice(CreateMilestoneInvoiceRequest $request, int $id): JsonResponse
    {
        if (! $request->user()->can('create_milestone_invoice')) {
            return ApiResponse::error(__('messages.unauthorized'), [], 403);
        }

        $milestone = ContractMilestone::with(['contract.project'])->findOrFail($id);

        try {
            $invoice = $this->createMilestoneInvoiceAction->execute($milestone, $request->validated());

            // Send immediately if requested
            if ($request->boolean('sendImmediately')) {
                if (! $request->user()->can('send_invoices')) {
                    return ApiResponse::error(__('messages.unauthorized_send_invoice'), [], 403);
                }
                $invoice = $this->sendInvoiceAction->execute($invoice);
            }

            return ApiResponse::success([
                'invoice' => new InvoiceResource($invoice),
                'milestone' => new MilestoneResource($milestone->fresh(['invoice'])),
            ], __('messages.invoice_created_from_milestone'), 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/milestones/{id}/link-invoice",
     *     operationId="linkMilestoneInvoice",
     *     tags={"Milestones"},
     *     summary="Link existing invoice to milestone",
     *     description="Links an existing invoice to the specified milestone",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Milestone ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"invoiceId"},
     *
     *             @OA\Property(property="invoiceId", type="integer", example=5, description="ID of the invoice to link")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoice linked successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice linked to milestone successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="milestone", ref="#/components/schemas/MilestoneResource")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Milestone already has invoice",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Milestone already has an invoice linked")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Milestone or Invoice not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Not found")
     *         )
     *     )
     * )
     */
    public function linkInvoice(LinkMilestoneInvoiceRequest $request, int $id): JsonResponse
    {
        if (! $request->user()->can('link_milestone_invoice')) {
            return ApiResponse::error(__('messages.unauthorized'), [], 403);
        }

        $milestone = ContractMilestone::findOrFail($id);
        $invoice = Invoice::withoutGlobalScopes()->findOrFail($request->invoiceId);

        try {
            $this->linkInvoiceToMilestoneAction->execute($invoice, $milestone);

            return ApiResponse::success([
                'milestone' => new MilestoneResource($milestone->fresh(['invoice'])),
            ], __('messages.invoice_linked_to_milestone'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 400);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/milestones/{id}/mark-paid",
     *     operationId="markMilestonePaid",
     *     tags={"Milestones"},
     *     summary="Mark milestone as paid",
     *     description="Marks a milestone as paid. If linked to an invoice, the invoice status is also updated.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Milestone ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="invoiceId", type="integer", example=5, description="Optional invoice ID to link")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Milestone marked as paid",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Milestone marked as paid"),
     *             @OA\Property(property="data", ref="#/components/schemas/MilestoneResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Milestone not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Milestone not found")
     *         )
     *     )
     * )
     */
    public function markPaid(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->can('mark_milestone_paid')) {
            return ApiResponse::error(__('messages.unauthorized'), [], 403);
        }

        $milestone = ContractMilestone::with(['invoice'])->findOrFail($id);

        return DB::transaction(function () use ($request, $milestone) {
            $invoiceId = $request->input('invoiceId');

            // If milestone has linked invoice, mark it as paid too
            if ($milestone->invoice) {
                $this->markInvoiceAsPaidAction->execute($milestone->invoice);
            }

            $milestone = $this->contractService->markMilestonePaid($milestone, $invoiceId);

            return ApiResponse::success(
                new MilestoneResource($milestone->load(['invoice'])),
                __('messages.milestone_marked_paid')
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/milestones/{id}",
     *     operationId="getMilestone",
     *     tags={"Milestones"},
     *     summary="Get milestone details",
     *     description="Retrieve a specific milestone with its linked invoice",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Milestone ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Milestone retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Milestone retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/MilestoneResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Milestone not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Milestone not found")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $milestone = ContractMilestone::with(['contract.project', 'invoice'])->findOrFail($id);

        return ApiResponse::success(
            new MilestoneResource($milestone),
            __('messages.milestone_retrieved')
        );
    }
}
