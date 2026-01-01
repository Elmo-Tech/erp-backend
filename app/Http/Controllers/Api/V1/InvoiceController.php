<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Invoices\StoreInvoiceRequest;
use App\Http\Requests\V1\Invoices\UpdateInvoiceRequest;
use App\Http\Resources\V1\Invoices\InvoiceCollection;
use App\Http\Resources\V1\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\Client\AuditLogService;
use App\Services\Client\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private AuditLogService $auditLogService
    ) {
        // Middleware is applied at route level: auth:sanctum, locale
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invoices",
     *     operationId="getInvoices",
     *     tags={"Invoices"},
     *     summary="Get all invoices with pagination and filtering",
     *     description="Retrieve a paginated list of invoices with optional filtering by status, project_id, and date range",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         description="Filter invoices by status",
     *         @OA\Schema(type="string", enum={"draft", "sent", "paid", "overdue", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[projectId]",
     *         in="query",
     *         description="Filter invoices by project ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[dateFrom]",
     *         in="query",
     *         description="Filter invoices created from this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="filter[dateTo]",
     *         in="query",
     *         description="Filter invoices created up to this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="filter[dueDateFrom]",
     *         in="query",
     *         description="Filter invoices with due date from this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="filter[dueDateTo]",
     *         in="query",
     *         description="Filter invoices with due date up to this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search invoices by invoice number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoices retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="invoices",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/InvoiceResource")
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=100),
     *                     @OA\Property(property="count", type="integer", example=15),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=7)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have admin permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Log admin data access
        $this->auditLogService->log('admin_invoices_viewed');

        // Use withoutGlobalScopes to bypass ClientScope for admin access
        $invoices = QueryBuilder::for(Invoice::class)
            ->withoutGlobalScopes() // Admin can see all invoices
            ->with(['project']) // Load project relationship
            ->allowedFilters([
                'status',
                AllowedFilter::exact('project_id', 'project_id')->ignore(null),
                AllowedFilter::callback('projectId', function ($query, $value) {
                    $query->where('project_id', $value);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('invoice_number', 'like', "%{$value}%");
                }),
                AllowedFilter::callback('dateFrom', function ($query, $value) {
                    $query->whereDate('created_at', '>=', $value);
                }),
                AllowedFilter::callback('dateTo', function ($query, $value) {
                    $query->whereDate('created_at', '<=', $value);
                }),
                AllowedFilter::callback('dueDateFrom', function ($query, $value) {
                    $query->whereDate('due_date', '>=', $value);
                }),
                AllowedFilter::callback('dueDateTo', function ($query, $value) {
                    $query->whereDate('due_date', '<=', $value);
                }),
            ])
            ->defaultSort('-created_at')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new InvoiceCollection($invoices),
            'Invoices retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invoices",
     *     operationId="createInvoice",
     *     tags={"Invoices"},
     *     summary="Create a new invoice",
     *     description="Create a new invoice for a project with auto-generated invoice number",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"projectId", "amount", "dueDate"},
     *             @OA\Property(property="projectId", type="integer", example=1),
     *             @OA\Property(property="amount", type="number", format="float", example=5000.00),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="dueDate", type="string", format="date", example="2025-02-15"),
     *             @OA\Property(property="notes", type="string", example="Payment for project milestone 1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Invoice created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/InvoiceResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for invoice creation"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have admin permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            // Find the project
            $project = Project::withoutGlobalScopes()->findOrFail($data['projectId']);

            // Create invoice using service
            $invoice = $this->invoiceService->createInvoice($project, $data);

            return ApiResponse::success(
                new InvoiceResource($invoice->load(['project'])),
                'Invoice created successfully',
                201
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invoices/{id}",
     *     operationId="getInvoice",
     *     tags={"Invoices"},
     *     summary="Get a single invoice",
     *     description="Retrieve a specific invoice by ID with related project data",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/InvoiceResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have admin permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        // Use withoutGlobalScopes to bypass ClientScope for admin access
        $invoice = Invoice::withoutGlobalScopes()
            ->with(['project'])
            ->findOrFail($id);

        // Log admin data access
        $this->auditLogService->log('admin_invoice_viewed', $invoice);

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Invoice retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/invoices/{id}",
     *     operationId="updateInvoice",
     *     tags={"Invoices"},
     *     summary="Update an existing invoice",
     *     description="Update an invoice with new data and audit trail",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", format="float", example=6000.00),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="status", type="string", enum={"draft", "sent", "paid", "overdue", "cancelled"}, example="sent"),
     *             @OA\Property(property="dueDate", type="string", format="date", example="2025-03-15"),
     *             @OA\Property(property="notes", type="string", example="Updated payment terms")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/InvoiceResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for invoice update"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have admin permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function update(UpdateInvoiceRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            // Use withoutGlobalScopes to bypass ClientScope for admin access
            $invoice = Invoice::withoutGlobalScopes()->findOrFail($id);
            
            $data = $request->validated();

            // Update invoice
            $invoice->update($data);

            // Log the update action
            $this->auditLogService->log('admin_invoice_updated', $invoice, [
                'updated_fields' => array_keys($data),
                'updated_data' => $data,
            ]);

            return ApiResponse::success(
                new InvoiceResource($invoice->fresh()->load(['project'])),
                'Invoice updated successfully'
            );
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/invoices/{id}",
     *     operationId="deleteInvoice",
     *     tags={"Invoices"},
     *     summary="Delete an invoice",
     *     description="Delete a specific invoice by ID",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have admin permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            // Use withoutGlobalScopes to bypass ClientScope for admin access
            $invoice = Invoice::withoutGlobalScopes()->findOrFail($id);

            // Log the deletion action before deleting
            $this->auditLogService->log('admin_invoice_deleted', $invoice, [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'project_id' => $invoice->project_id,
            ]);

            $invoice->delete();

            return ApiResponse::success(
                null,
                'Invoice deleted successfully'
            );
        });
    }
}