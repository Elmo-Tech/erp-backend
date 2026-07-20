<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinanceInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        $clientId = $this->input('clientId', $this->route('invoice')?->client_id);

        return [
            'clientId' => ['sometimes', 'integer', 'exists:clients,id'],
            'projectId' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where(fn ($query) => $query->where('client_id', $clientId)),
            ],
            'invoiceType' => ['sometimes', 'string', 'in:one_time,subscription'],
            'billingCycle' => [
                Rule::requiredIf(fn () => $this->input('invoiceType', $this->route('invoice')?->invoice_type) === 'subscription'),
                'nullable',
                'string',
                'in:monthly,quarterly,semi_annual,annual',
            ],
            'issueDate' => ['sometimes', 'date'],
            'dueDate' => ['sometimes', 'date'],
            'taxRate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'discountAmount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'in:draft,sent,paid,overdue'],
            'notes' => ['sometimes', 'nullable', 'string'],

            // Items are optional on update — if provided, they replace existing items
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unitPrice' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
