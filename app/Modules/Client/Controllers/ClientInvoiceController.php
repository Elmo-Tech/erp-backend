<?php

namespace App\Modules\Client\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Client\Actions\LogClientAuditAction;
use App\Modules\Client\Actions\MarkInvoiceAsPaidAction;
use App\Modules\Client\Resources\ClientInvoiceCollection;
use App\Modules\Client\Resources\ClientInvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenApi\Annotations as OA;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClientInvoiceController extends Controller
{
    public function __construct(
        private MarkInvoiceAsPaidAction $markInvoiceAsPaidAction,
        private LogClientAuditAction $auditLogService
    ) {
        // Middleware is applied at route level: auth:sanctum, locale, role:client
    }

    /**
     * @OA\Get(
     *     path="/api/v1/client/invoices",
     *     operationId="getClientInvoices",
     *     tags={"Client Portal"},
     *     summary="Get client's invoices with pagination",
     *     description="Retrieve a paginated list of invoices for the authenticated client with limited data visibility (Black Box principle)",
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
     *         name="filter[status]",
     *         in="query",
     *         description="Filter invoices by status",
     *
     *         @OA\Schema(type="string", enum={"draft", "sent", "paid", "overdue", "cancelled"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[projectId]",
     *         in="query",
     *         description="Filter invoices by project ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search invoices by invoice number",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Client invoices retrieved successfully",
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
     *                     @OA\Items(ref="#/components/schemas/ClientInvoiceResource")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=25),
     *                     @OA\Property(property="count", type="integer", example=15),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have client role",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Log client data access
        $this->auditLogService->log('client_invoices_viewed');

        // ClientScope automatically filters to only this client's invoices
        $invoices = QueryBuilder::for(Invoice::class)
            ->with(['project']) // Load project for additional context if needed
            ->allowedFilters([
                'status',
                AllowedFilter::exact('project_id', 'project_id')->ignore(null),
                AllowedFilter::callback('projectId', function ($query, $value) {
                    $query->where('project_id', $value);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('invoice_number', 'like', "%{$value}%");
                }),
            ])
            ->defaultSort('-created_at')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new ClientInvoiceCollection($invoices),
            'Invoices retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/client/invoices/{id}",
     *     operationId="getClientInvoice",
     *     tags={"Client Portal"},
     *     summary="Get a single client invoice",
     *     description="Retrieve a specific invoice for the authenticated client with limited data visibility (Black Box principle)",
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
     *         description="Invoice ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Client invoice retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ClientInvoiceResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found or not accessible to client",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have client role",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function show(Invoice $invoice): JsonResponse
    {
        // Log client data access
        $this->auditLogService->log('client_invoice_viewed', $invoice);

        // ClientScope and InvoicePolicy ensure client can only see their own invoices
        return ApiResponse::success(
            new ClientInvoiceResource($invoice->load(['project'])),
            'Invoice retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/client/invoices/{id}/pay",
     *     operationId="payClientInvoice",
     *     tags={"Client Portal"},
     *     summary="Pay an invoice",
     *     description="Mark an invoice as paid for the authenticated client. Only payable invoices (sent or overdue status) can be paid.",
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
     *         description="Invoice ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoice paid successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice paid successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ClientInvoiceResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation - invoice cannot be paid in current status",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice cannot be paid in current status")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found or not accessible to client",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have client role or cannot pay this invoice",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function pay(Invoice $invoice): JsonResponse
    {
        // InvoicePolicy ensures client can only pay their own invoices
        // and that the invoice is in a payable state

        return DB::transaction(function () use ($invoice) {
            try {
                $paidInvoice = $this->markInvoiceAsPaidAction->execute($invoice);

                // Log the payment action
                $this->auditLogService->log('client_invoice_paid', $paidInvoice, [
                    'amount' => $paidInvoice->amount,
                    'currency' => $paidInvoice->currency,
                    'paid_at' => $paidInvoice->paid_at->toISOString(),
                    'invoice_number' => $paidInvoice->invoice_number,
                ]);

                return ApiResponse::success(
                    new ClientInvoiceResource($paidInvoice->load(['project'])),
                    'Invoice paid successfully'
                );
            } catch (InvalidArgumentException $e) {
                return ApiResponse::error($e->getMessage(), 400);
            }
        });
    }
}
