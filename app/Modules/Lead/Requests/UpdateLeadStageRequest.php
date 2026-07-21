<?php

namespace App\Modules\Lead\Requests;

use App\Enums\DocumentCreationType;
use App\Enums\LeadStage;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $creationTypes = DocumentCreationType::values();
        $documentTypes = ['proposal', 'quotation', 'contract'];

        return [
            // ── Top-level ─────────────────────────────────────────────────────
            'new_stage' => ['required', 'string', Rule::enum(LeadStage::class)],
            'stage_results' => ['present', 'array'],
            'scheduled_meeting' => ['nullable', 'array'],
            'scheduled_meeting.meeting_type' => ['required_with:scheduled_meeting', 'string', 'max:100'],
            'scheduled_meeting.date' => ['required_with:scheduled_meeting', 'date'],
            'scheduled_meeting.time' => ['required_with:scheduled_meeting', 'string', 'max:10'],
            'scheduled_meeting.location_type' => ['nullable', 'string', 'in:online,client_site,our_office,office,other'],
            'scheduled_meeting.location_details' => ['nullable', 'string', 'max:1000'],
            'scheduled_meeting.internal_attendees' => ['nullable', 'array'],
            'scheduled_meeting.internal_attendees.*' => ['integer', 'exists:users,id'],
            'scheduled_meeting.external_attendees' => ['nullable', 'array'],
            'scheduled_meeting.external_attendees.*' => ['string'],
            'scheduled_meeting.notes' => ['nullable', 'string', 'max:5000'],

            // ── Each stage result entry ───────────────────────────────────────
            'stage_results.*.stage' => ['required', 'string', Rule::in([
                LeadStage::MEETING->value,
                LeadStage::PROPOSAL->value,
                LeadStage::QUOTATION->value,
                LeadStage::CONTRACT->value,
            ])],
            'stage_results.*.type' => ['required', 'string', Rule::in($documentTypes)],
            'stage_results.*.creation_type' => ['required', 'string', Rule::in($creationTypes)],
            'stage_results.*.skip_reason' => [
                'nullable',
                'string',
                'max:500',
                // Required when creation_type is 'skipped'
                Rule::requiredIf(function () {
                    // We validate this at the action level; here we just allow it through.
                    return false;
                }),
            ],
            'stage_results.*.status' => ['nullable', 'string', 'max:50'],
            'stage_results.*.assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'stage_results.*.due_date' => ['nullable', 'date'],
            'stage_results.*.upload_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:51200'],
            'stage_results.*.notes' => ['nullable', 'string', 'max:5000'],

            // ── Meeting-specific ──────────────────────────────────────────────
            'stage_results.*.meeting_type' => ['nullable', 'string', 'max:50'],
            'stage_results.*.date' => ['nullable', 'date'],
            'stage_results.*.time' => ['nullable', 'string', 'max:10'],
            'stage_results.*.location_type' => ['nullable', 'string', 'in:online,client_site,our_office,other'],
            'stage_results.*.location_details' => ['nullable', 'string', 'max:1000'],
            'stage_results.*.internal_attendees' => ['nullable', 'array'],
            'stage_results.*.internal_attendees.*' => ['integer', 'exists:users,id'],
            'stage_results.*.external_attendees' => ['nullable', 'array'],
            'stage_results.*.external_attendees.*' => ['string'],

            // ── Proposal-specific ─────────────────────────────────────────────
            'stage_results.*.proposal_source' => ['nullable', 'string', 'in:upload_new,use_template'],
            'stage_results.*.template_id' => ['nullable', 'integer', 'min:1'],
            'stage_results.*.validity_date' => ['nullable', 'date'],

            // ── Quotation-specific ────────────────────────────────────────────
            'stage_results.*.currency' => ['nullable', 'string', 'max:10'],
            'stage_results.*.base_price' => ['nullable', 'numeric', 'min:0'],
            'stage_results.*.commission_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'stage_results.*.final_total' => ['nullable', 'numeric', 'min:0'],
            'stage_results.*.payment_stages' => ['nullable', 'array'],
            'stage_results.*.payment_stages.*.stage_name' => ['required_with:stage_results.*.payment_stages', 'string', 'max:255'],
            'stage_results.*.payment_stages.*.percentage' => ['required_with:stage_results.*.payment_stages', 'numeric', 'min:0', 'max:100'],
            'stage_results.*.payment_stages.*.amount' => ['required_with:stage_results.*.payment_stages', 'numeric', 'min:0'],

            // ── Contract-specific ─────────────────────────────────────────────
            'stage_results.*.contract_model' => ['nullable', 'string', 'in:standard,saas_subscription'],
            'stage_results.*.development_days' => ['nullable', 'integer', 'min:1'],
            'stage_results.*.review_days' => ['nullable', 'integer', 'min:0'],
            'stage_results.*.testing_days' => ['nullable', 'integer', 'min:0'],
            'stage_results.*.delay_penalty' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stage_results.*.source_code_handover' => ['nullable', 'boolean'],
            'stage_results.*.execution_start_date' => ['nullable', 'date'],
            'stage_results.*.working_days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],

            // Contract SaaS-specific
            'stage_results.*.setup_fee' => ['nullable', 'numeric', 'min:0'],
            'stage_results.*.recurring_fee' => ['nullable', 'numeric', 'min:0'],
            'stage_results.*.billing_cycle' => ['nullable', 'string', 'in:monthly,quarterly,semi_annual,annual'],
            'stage_results.*.subscription_start_date' => ['nullable', 'date'],
            'stage_results.*.apply_promo' => ['nullable', 'boolean'],
            'stage_results.*.promo_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stage_results.*.addons' => ['nullable', 'array'],
            'stage_results.*.addons.*.name' => ['required_with:stage_results.*.addons', 'string', 'max:255'],
            'stage_results.*.addons.*.price' => ['required_with:stage_results.*.addons', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_stage.required' => 'The destination stage is required.',
            'new_stage.Illuminate\Validation\Rules\Enum' => 'The provided stage is not a valid pipeline stage.',
            'stage_results.present' => 'The stage_results field must be present (use an empty array if none).',
            'stage_results.*.type.required' => 'Each stage result must specify a document type.',
            'stage_results.*.stage.required' => 'Each stage result must specify the pipeline stage it completes.',
            'stage_results.*.type.in' => 'Document type must be one of: proposal, quotation, contract.',
            'stage_results.*.creation_type.required' => 'Each stage result must specify a creation type.',
            'stage_results.*.creation_type.in' => 'Creation type must be one of: task, direct_upload, skipped.',
            'scheduled_meeting.meeting_type.required_with' => 'Meeting type is required.',
            'scheduled_meeting.date.required_with' => 'Meeting date is required.',
            'scheduled_meeting.time.required_with' => 'Meeting time is required.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $expectedTypes = [
                LeadStage::MEETING->value => 'proposal',
                LeadStage::PROPOSAL->value => 'proposal',
                LeadStage::QUOTATION->value => 'quotation',
                LeadStage::CONTRACT->value => 'contract',
            ];

            foreach ((array) $this->input('stage_results', []) as $index => $stageEvidence) {
                if (! is_array($stageEvidence)) {
                    continue;
                }

                $stage = $stageEvidence['stage'] ?? null;
                $type = $stageEvidence['type'] ?? null;
                $creationType = $stageEvidence['creation_type'] ?? null;

                if (is_string($stage) && is_string($type) && ($expectedTypes[$stage] ?? null) !== $type) {
                    $validator->errors()->add(
                        "stage_results.{$index}.type",
                        "Document type does not match the completed {$stage} stage."
                    );
                }

                if ($creationType === DocumentCreationType::TASK->value) {
                    if (empty($stageEvidence['assigned_to'])) {
                        $validator->errors()->add("stage_results.{$index}.assigned_to", 'An assignee is required when creating a task.');
                    }

                    if (empty($stageEvidence['due_date'])) {
                        $validator->errors()->add("stage_results.{$index}.due_date", 'A due date is required when creating a task.');
                    }
                }

                $usesTemplate = ($stageEvidence['proposal_source'] ?? null) === 'use_template';

                if ($creationType === DocumentCreationType::DIRECT_UPLOAD->value && ! $usesTemplate && ! $this->hasFile("stage_results.{$index}.upload_file")) {
                    $validator->errors()->add("stage_results.{$index}.upload_file", 'A document file is required for direct upload.');
                }
            }
        }];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('validation.failed'),
                422,
                $validator->errors()->toArray()
            )
        );
    }
}
