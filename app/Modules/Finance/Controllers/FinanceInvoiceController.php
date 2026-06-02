<?php

namespace App\Modules\Finance\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\CalculateInvoiceTotalsAction;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\FinanceInvoiceItem;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Finance\Requests\StoreFinanceInvoiceRequest;
use App\Modules\Finance\Requests\UpdateFinanceInvoiceRequest;
use App\Modules\Finance\Resources\FinanceInvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceInvoiceController extends Controller
{
    public function __construct(
        private readonly CalculateInvoiceTotalsAction $calculateTotals,
    ) {}

    /**
     * GET /finance/invoices
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_invoices'), 403, 'Unauthorized');

        $query = FinanceInvoice::with(['client', 'project'])->latest();

        // Filter by client when the caller passes ?client_id=X (e.g. Client Ledger tab)
        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->client_id);
        }

        $invoices = $query->paginate(20);

        return ApiResponse::success(
            FinanceInvoiceResource::collection($invoices)->response()->getData(true),
            'Invoices retrieved successfully'
        );
    }

    /**
     * POST /finance/invoices
     */
    public function store(StoreFinanceInvoiceRequest $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_invoices'), 403, 'Unauthorized');

        $data  = $request->validated();
        $items = $data['items'];

        $totals = $this->calculateTotals->execute(
            items: array_map(fn ($i) => [
                'quantity'   => $i['quantity'],
                'unit_price' => $i['unitPrice'],
            ], $items),
            taxRate:        $data['taxRate'] ?? 0,
            discountAmount: $data['discountAmount'] ?? 0,
        );

        $invoice = DB::transaction(function () use ($data, $items, $totals): FinanceInvoice {
            $invoice = FinanceInvoice::create([
                'client_id'       => $data['clientId'],
                'project_id'      => $data['projectId'] ?? null,
                'invoice_number'  => FinanceInvoice::generateInvoiceNumber(),
                'invoice_type'    => $data['invoiceType'] ?? 'one_time',
                'billing_cycle'   => ($data['invoiceType'] ?? 'one_time') === 'subscription' ? $data['billingCycle'] : null,
                'issue_date'      => $data['issueDate'],
                'due_date'        => $data['dueDate'],
                'tax_rate'        => $data['taxRate'] ?? 0,
                'discount_amount' => $data['discountAmount'] ?? 0,
                'status'          => $data['status'] ?? 'draft',
                'notes'           => $data['notes'] ?? null,
                'subtotal'        => $totals['subtotal'],
                'tax_amount'      => $totals['tax_amount'],
                'total_amount'    => $totals['total_amount'],
            ]);

            foreach ($items as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unitPrice'],
                    'total'       => round((int) $item['quantity'] * (float) $item['unitPrice'], 2),
                ]);
            }

            $this->syncSubscription($invoice);

            return $invoice;
        });

        return ApiResponse::success(
            new FinanceInvoiceResource($invoice->load(['client', 'project', 'items'])),
            'Invoice created successfully',
            201
        );
    }

    /**
     * GET /finance/invoices/{invoice}
     */
    public function show(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        abort_if(! $request->user()->can('manage_invoices'), 403, 'Unauthorized');

        $invoice->load(['client', 'project', 'items']);

        return ApiResponse::success(
            new FinanceInvoiceResource($invoice),
            'Invoice retrieved successfully'
        );
    }

    /**
     * PUT /finance/invoices/{invoice}
     */
    public function update(UpdateFinanceInvoiceRequest $request, FinanceInvoice $invoice): JsonResponse
    {
        abort_if(! $request->user()->can('manage_invoices'), 403, 'Unauthorized');

        $data = $request->validated();

        // Re-calculate totals if items or financial fields are being updated
        $items = $data['items'] ?? null;

        if ($items !== null) {
            $totals = $this->calculateTotals->execute(
                items: array_map(fn ($i) => [
                    'quantity'   => $i['quantity'],
                    'unit_price' => $i['unitPrice'],
                ], $items),
                taxRate:        $data['taxRate'] ?? $invoice->tax_rate,
                discountAmount: $data['discountAmount'] ?? $invoice->discount_amount,
            );
        } elseif (isset($data['taxRate']) || isset($data['discountAmount'])) {
            // Items unchanged but tax/discount changed — recalculate from existing items
            $existingItems = $invoice->items->map(fn ($i) => [
                'quantity'   => $i->quantity,
                'unit_price' => $i->unit_price,
            ])->toArray();

            $totals = $this->calculateTotals->execute(
                items:          $existingItems,
                taxRate:        $data['taxRate'] ?? $invoice->tax_rate,
                discountAmount: $data['discountAmount'] ?? $invoice->discount_amount,
            );
        }

        DB::transaction(function () use ($invoice, $data, $items, &$totals): void {
            $invoice->update(array_filter([
                'client_id'       => $data['clientId'] ?? null,
                'project_id'      => array_key_exists('projectId', $data) ? $data['projectId'] : $invoice->project_id,
                'invoice_type'    => $data['invoiceType'] ?? null,
                'billing_cycle'   => ($data['invoiceType'] ?? $invoice->invoice_type) === 'subscription'
                    ? (array_key_exists('billingCycle', $data) ? $data['billingCycle'] : $invoice->billing_cycle)
                    : null,
                'issue_date'      => $data['issueDate'] ?? null,
                'due_date'        => $data['dueDate'] ?? null,
                'tax_rate'        => $data['taxRate'] ?? null,
                'discount_amount' => $data['discountAmount'] ?? null,
                'status'          => $data['status'] ?? null,
                'notes'           => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
                'subtotal'        => $totals['subtotal'] ?? null,
                'tax_amount'      => $totals['tax_amount'] ?? null,
                'total_amount'    => $totals['total_amount'] ?? null,
            ], fn ($v) => $v !== null));

            // Replace items if provided
            if ($items !== null) {
                $invoice->items()->delete();

                foreach ($items as $item) {
                    $invoice->items()->create([
                        'description' => $item['description'],
                        'quantity'    => $item['quantity'],
                        'unit_price'  => $item['unitPrice'],
                        'total'       => round((int) $item['quantity'] * (float) $item['unitPrice'], 2),
                    ]);
                }
            }

            $invoice->refresh();
            $this->syncSubscription($invoice);
        });

        return ApiResponse::success(
            new FinanceInvoiceResource($invoice->fresh(['client', 'project', 'items'])),
            'Invoice updated successfully'
        );
    }

    /**
     * DELETE /finance/invoices/{invoice}
     */
    public function destroy(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        abort_if(! $request->user()->can('manage_invoices'), 403, 'Unauthorized');

        $invoice->delete();

        return ApiResponse::success(null, 'Invoice deleted successfully');
    }

    private function syncSubscription(FinanceInvoice $invoice): void
    {
        if ($invoice->invoice_type !== 'subscription') {
            if ($invoice->subscription_id !== null) {
                $invoice->forceFill(['subscription_id' => null, 'billing_cycle' => null])->save();
            }

            return;
        }

        $invoice->loadMissing(['client', 'project']);

        $subscription = $invoice->subscription_id
            ? Subscription::find($invoice->subscription_id)
            : new Subscription();

        $subscription->fill([
            'client_id' => $invoice->client_id,
            'project_id' => $invoice->project_id,
            'name' => 'Invoice '.$invoice->invoice_number,
            'provider' => $invoice->project?->title,
            'billing_cycle' => $invoice->billing_cycle,
            'cost' => $invoice->total_amount,
            'currency' => 'USD',
            'next_billing_date' => $invoice->due_date,
            'status' => $invoice->status === 'paid' ? 'active' : 'active',
            'notes' => $invoice->notes,
        ]);
        $subscription->save();

        if ($invoice->subscription_id !== $subscription->id) {
            $invoice->forceFill(['subscription_id' => $subscription->id])->save();
        }
    }
}
