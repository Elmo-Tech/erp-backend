<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinanceInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'clientId'       => ['required', 'integer', 'exists:clients,id'],
            'projectId'      => ['nullable', 'integer', 'exists:projects,id'],
            'issueDate'      => ['required', 'date'],
            'dueDate'        => ['required', 'date', 'after_or_equal:issueDate'],
            'taxRate'        => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'discountAmount' => ['sometimes', 'numeric', 'min:0'],
            'status'         => ['sometimes', 'string', 'in:draft,sent,paid,overdue'],
            'notes'          => ['nullable', 'string'],

            // Line items — at least one required
            'items'              => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unitPrice'   => ['required', 'numeric', 'min:0'],
        ];
    }
}
