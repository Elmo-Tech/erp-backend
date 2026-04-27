<?php

namespace App\Modules\Invoice\Controllers;

use App\Enums\InvoiceStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Invoice\Actions\CreateInvoiceAction;
use App\Modules\Invoice\Actions\GenerateInvoicePdfAction;
use App\Modules\Invoice\Actions\UpdateInvoiceStatusAction;
use App\Modules\Invoice\Requests\Invoices\MarkAsPaidRequest;
use App\Modules\Invoice\Requests\Invoices\StoreInvoiceRequest;
use App\Modules\Invoice\Requests\Invoices\UpdateInvoiceRequest;
use App\Modules\Invoice\Resources\Invoices\InvoiceCollection;
use App\Modules\Invoice\Resources\Invoices\InvoiceResource;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use App\Services\AuditService;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 *     name="Invoices",
 *     description="Invoice management endpoints"
 * )
 */
class InvoiceController extends Controller
{
    public function __construct(
        private BillingService $billingService,
        private AuditService $auditService,
        private CreateInvoiceAction $createInvoice,
        private UpdateInvoiceStatusAction $updateInvoiceStatus,
        private GenerateInvoicePdfAction $generateInvoicePdf
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invoices",
     *     summary="Get paginated list of invoices",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search in invoice number, milestone name, or notes",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         description="Filter by invoice status",
     *
     *         @OA\Schema(type="string", enum={"draft", "sent", "paid", "overdue", "cancelled"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[project_id]",
     *         in="query",
     *         description="Filter by project ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[contract_id]",
     *         in="query",
     *         description="Filter by contract ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Items per page",
     *
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoices retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="invoices",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/InvoiceResource")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="count", type="integer"),
     *                     @OA\Property(property="perPage", type="integer"),
     *                     @OA\Property(property="currentPage", type="integer"),
     *                     @OA\Property(property="totalPages", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $invoices = QueryBuilder::for(Invoice::class)
            ->with(['project', 'contract', 'createdBy', 'updatedBy'])
            ->allowedFilters([
                AllowedFilter::partial('search', 'invoice_number'),
                AllowedFilter::partial('search', 'milestone_name'),
                AllowedFilter::partial('search', 'notes'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('project_id'),
                AllowedFilter::exact('contract_id'),
                AllowedFilter::callback('projectId', function ($query, $value) {
                    $query->where('project_id', $value);
                }),
                AllowedFilter::callback('dateFrom', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('dateTo', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
            ])
            ->defaultSort('-created_at')
            ->allowedSorts(['created_at', 'due_date', 'amount', 'status'])
            ->paginate($request->get('perPage', 10));

        return ApiResponse::success(
            new InvoiceCollection($invoices),
            __('messages.invoices_retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invoices",
     *     summary="Create a new invoice",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="projectId", type="integer", example=1),
     *             @OA\Property(property="contractId", type="integer", example=1),
     *             @OA\Property(property="milestoneName", type="string", example="Project Kickoff"),
     *             @OA\Property(property="amount", type="number", format="float", example=5000.00),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="dueDate", type="string", format="date", example="2026-03-01"),
     *             @OA\Property(property="notes", type="string", example="Invoice for project kickoff milestone")
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
     *             @OA\Property(property="message", type="string", example="Invoice created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/InvoiceResource")
     *         )
     *     )
     * )
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->createInvoice->execute($request->validated(), $request->user()?->id);

        return ApiResponse::success(
            new InvoiceResource($invoice),
            __('messages.invoice_created'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invoices/{id}",
     *     summary="Get invoice details",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoice retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/InvoiceResource")
     *         )
     *     )
     * )
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['project', 'contract', 'createdBy', 'updatedBy']);

        return ApiResponse::success(
            new InvoiceResource($invoice),
            __('messages.invoice_retrieved')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/invoices/{id}",
     *     summary="Update invoice",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="amount", type="number", format="float", example=5500.00),
     *             @OA\Property(property="dueDate", type="string", format="date", example="2026-03-15"),
     *             @OA\Property(property="notes", type="string", example="Updated invoice notes")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoice updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/InvoiceResource")
     *         )
     *     )
     * )
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validated();

        $oldData = $invoice->toArray();

        $invoice->update([
            'amount' => $data['amount'] ?? $invoice->amount,
            'due_date' => $data['dueDate'] ?? $invoice->due_date,
            'notes' => $data['notes'] ?? $invoice->notes,
            'updated_by' => auth()->id(),
        ]);

        // Log the update
        $this->auditService->logInvoiceEvent(
            action: 'updated',
            invoice: $invoice,
            metadata: [
                'old_data' => $oldData,
                'new_data' => $invoice->fresh()->toArray(),
            ]
        );

        return ApiResponse::success(
            new InvoiceResource($invoice->load(['project', 'contract', 'createdBy', 'updatedBy'])),
            __('messages.invoice_updated')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invoices/{id}/send",
     *     summary="Send invoice to client",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoice sent successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice sent successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/InvoiceResource")
     *         )
     *     )
     * )
     */
    public function send(Invoice $invoice): JsonResponse
    {
        $updatedInvoice = $this->updateInvoiceStatus->send($invoice, auth()->id());

        return ApiResponse::success(
            new InvoiceResource($updatedInvoice->load(['project', 'contract', 'createdBy', 'updatedBy'])),
            __('messages.invoice_sent')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invoices/{id}/mark-paid",
     *     summary="Mark invoice as paid",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="paymentMethod", type="string", example="bank_transfer"),
     *             @OA\Property(property="transactionId", type="string", example="TXN123456"),
     *             @OA\Property(property="paymentNotes", type="string", example="Payment received via bank transfer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoice marked as paid successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice marked as paid successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/InvoiceResource")
     *         )
     *     )
     * )
     */
    public function markAsPaid(MarkAsPaidRequest $request, Invoice $invoice): JsonResponse
    {
        $paymentDetails = $request->validated();

        $updatedInvoice = $this->updateInvoiceStatus->markAsPaid($invoice, $paymentDetails, $request->user()?->id);

        return ApiResponse::success(
            new InvoiceResource($updatedInvoice->load(['project', 'contract', 'createdBy', 'updatedBy'])),
            __('messages.invoice_marked_paid')
        );
    }

    public function download(Invoice $invoice): Response
    {
        $pdf = $this->generateInvoicePdf->execute($invoice);

        return $pdf->download($this->generateInvoicePdf->filename($invoice));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/{id}/generate-milestone-invoice",
     *     summary="Generate invoice for project milestone",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="milestoneName", type="string", example="UI/UX Completion", enum={"Project Kickoff", "UI/UX Completion", "Development Completion", "Final Delivery"})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Milestone invoice generated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Milestone invoice generated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/InvoiceResource")
     *         )
     *     )
     * )
     */
    public function generateMilestoneInvoice(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'milestoneName' => 'required|string|in:Project Kickoff,UI/UX Completion,Development Completion,Final Delivery',
        ]);

        $invoice = $this->billingService->generateForMilestone($project, $request->milestoneName);

        return ApiResponse::success(
            new InvoiceResource($invoice->load(['project', 'contract', 'createdBy'])),
            __('messages.milestone_invoice_generated'),
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/{id}/generate-pending-invoices",
     *     summary="Generate all pending milestone invoices for project",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Pending invoices generated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pending invoices generated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="invoices",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/InvoiceResource")
     *                 ),
     *
     *                 @OA\Property(property="count", type="integer", example=2)
     *             )
     *         )
     *     )
     * )
     */
    public function generatePendingInvoices(Project $project): JsonResponse
    {
        $invoices = $this->billingService->generatePendingInvoices($project);

        return ApiResponse::success([
            'invoices' => InvoiceResource::collection(collect($invoices)->load(['project', 'contract', 'createdBy'])),
            'count' => count($invoices),
        ], __('messages.pending_invoices_generated'), 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/invoices/{id}",
     *     summary="Delete invoice",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoice deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice deleted successfully")
     *         )
     *     )
     * )
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        // Only allow deletion of draft invoices
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return ApiResponse::error(__('messages.cannot_delete_non_draft_invoice'), 422);
        }

        // Log the deletion before deleting
        $this->auditService->logInvoiceEvent(
            action: 'deleted',
            invoice: $invoice,
            metadata: [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'milestone_name' => $invoice->milestone_name,
            ],
            severity: 'warning'
        );

        $invoice->delete();

        return ApiResponse::success(null, __('messages.invoice_deleted'));
    }
}
