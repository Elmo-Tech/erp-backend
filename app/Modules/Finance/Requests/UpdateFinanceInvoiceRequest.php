<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinanceInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'clientId'       => ['sometimes', 'integer', 'exists:clients,id'],
            'projectId'      => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'issueDate'      => ['sometimes', 'date'],
            'dueDate'        => ['sometimes', 'date'],
            'taxRate'        => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'discountAmount' => ['sometimes', 'numeric', 'min:0'],
            'status'         => ['sometimes', 'string', 'in:draft,sent,paid,overdue'],
            'notes'          => ['sometimes', 'nullable', 'string'],

            // Items are optional on update — if provided, they replace existing items
            'items'               => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity'    => ['required_with:items', 'integer', 'min:1'],
            'items.*.unitPrice'   => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
