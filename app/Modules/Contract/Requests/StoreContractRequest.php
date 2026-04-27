<?php

namespace App\Modules\Contract\Requests;

use App\Enums\MilestoneTriggerType;
use App\Enums\QuotationStatus;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dataSource' => ['required', 'string', 'in:from_quotation,new_terms'],
            'title' => ['required', 'string', 'max:255'],
            'expiryDays' => ['required', 'integer', 'min:1'],
            'projectDuration' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'deliverables' => ['nullable', 'array', 'min:1'],
            'deliverables.*' => ['required', 'string', 'max:500'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after:startDate'],
            'preparationDeadline' => ['nullable', 'date', 'after_or_equal:startDate'],
            'fileNumber' => ['nullable', 'string', 'max:50', 'unique:contracts,file_number'],
            'quotationId' => ['nullable', 'integer', 'exists:quotations,id'],
            'projectId' => ['nullable', 'integer', 'exists:projects,id'],
            'templateId' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'taskId' => ['nullable', 'integer', 'exists:tasks,id'],
            'partyOne' => ['nullable', 'array'],
            'partyOne.company' => ['nullable', 'string', 'max:255'],
            'partyOne.representative' => ['nullable', 'string', 'max:255'],
            'partyOne.title' => ['nullable', 'string', 'max:100'],
            'partyOne.address' => ['nullable', 'string'],
            'partyTwo' => ['nullable', 'array'],
            'partyTwo.company' => ['nullable', 'string', 'max:255'],
            'partyTwo.name' => ['nullable', 'string', 'max:255'],
            'partyTwo.representative' => ['nullable', 'string', 'max:255'],
            'partyTwo.title' => ['nullable', 'string', 'max:100'],
            'partyTwo.address' => ['nullable', 'string'],
            'totalAmount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'paymentTerms' => ['nullable', 'string'],
            'milestones' => ['nullable', 'array', 'min:1'],
            'milestones.*.title' => ['required_with:milestones', 'string', 'max:255'],
            'milestones.*.description' => ['nullable', 'string'],
            'milestones.*.percentage' => ['required_with:milestones', 'numeric', 'min:0', 'max:100'],
            'milestones.*.dueDate' => ['nullable', 'date'],
            'milestones.*.triggerType' => ['nullable', Rule::enum(MilestoneTriggerType::class)],
            'milestones.*.triggerValue' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $dataSource = $this->input('dataSource');
            $quotationId = $this->input('quotationId');
            $milestones = $this->input('milestones', []);

            if ($dataSource === 'from_quotation') {
                if (! $quotationId) {
                    $validator->errors()->add('quotationId', __('contracts.quotation_required_for_from_quotation'));
                } else {
                    $quotation = \App\Models\Quotation::find($quotationId);
                    if ($quotation && $quotation->status !== QuotationStatus::APPROVED) {
                        $validator->errors()->add('quotationId', __('contracts.quotation_must_be_approved'));
                    }
                }
            }

            if ($dataSource === 'new_terms') {
                if (! $this->filled('totalAmount')) {
                    $validator->errors()->add('totalAmount', __('validation.required', ['attribute' => 'totalAmount']));
                }

                if (! $this->filled('partyOne.company')) {
                    $validator->errors()->add('partyOne.company', __('validation.required', ['attribute' => 'partyOne.company']));
                }

                if (! $this->filled('partyOne.representative')) {
                    $validator->errors()->add('partyOne.representative', __('validation.required', ['attribute' => 'partyOne.representative']));
                }

                if (! $this->filled('partyTwo.representative')) {
                    $validator->errors()->add('partyTwo.representative', __('validation.required', ['attribute' => 'partyTwo.representative']));
                }

                if (empty($milestones)) {
                    $validator->errors()->add('milestones', __('contracts.milestones_required'));
                }

                if (! empty($milestones)) {
                    $totalPercentage = array_sum(array_column($milestones, 'percentage'));
                    if (round($totalPercentage, 2) !== 100.00) {
                        $validator->errors()->add('milestones', __('contracts.milestones_must_equal_100', ['total' => $totalPercentage]));
                    }
                }
            }

            $deliverables = $this->input('deliverables', []);
            if ($dataSource === 'new_terms' && empty($deliverables)) {
                $validator->errors()->add('deliverables', __('contracts.deliverables_required'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'dataSource.required' => __('validation.required', ['attribute' => 'dataSource']),
            'dataSource.in' => __('validation.in', ['attribute' => 'dataSource']),
            'title.required' => __('validation.required', ['attribute' => 'title']),
            'expiryDays.required' => __('validation.required', ['attribute' => 'expiryDays']),
            'expiryDays.min' => __('validation.min.numeric', ['attribute' => 'expiryDays', 'min' => 1]),
            'projectDuration.required' => __('validation.required', ['attribute' => 'projectDuration']),
            'content.required' => __('validation.required', ['attribute' => 'content']),
            'deliverables.required' => __('validation.required', ['attribute' => 'deliverables']),
            'deliverables.min' => __('contracts.at_least_one_deliverable'),
            'startDate.required' => __('validation.required', ['attribute' => 'startDate']),
            'endDate.required' => __('validation.required', ['attribute' => 'endDate']),
            'endDate.after' => __('validation.after', ['attribute' => 'endDate', 'date' => 'startDate']),
            'totalAmount.required' => __('validation.required', ['attribute' => 'totalAmount']),
            'totalAmount.min' => __('validation.min.numeric', ['attribute' => 'totalAmount', 'min' => 0]),
            'milestones.required' => __('contracts.milestones_required'),
            'milestones.*.title.required' => __('validation.required', ['attribute' => 'milestone title']),
            'milestones.*.percentage.required' => __('validation.required', ['attribute' => 'milestone percentage']),
            'partyOne.required' => __('validation.required', ['attribute' => 'partyOne']),
            'partyTwo.required' => __('validation.required', ['attribute' => 'partyTwo']),
            'fileNumber.unique' => __('validation.unique', ['attribute' => 'fileNumber']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('contracts.validation_failed')
            )
        );
    }
}
