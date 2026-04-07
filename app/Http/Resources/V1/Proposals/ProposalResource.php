<?php

namespace App\Http\Resources\V1\Proposals;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProposalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'proposalId' => $this->id,
            'proposalNumber' => $this->proposal_number ?? '',
            'leadId' => $this->lead_id ?? 0,
            'templateId' => $this->template_id ?? 0,
            'title' => $this->title ?? '',
            'content' => $this->content ?? '',
            'filePath' => $this->file_path ?? '',
            'fileName' => $this->file_name ?? '',
            'fileUrl' => $this->file_url ?? '',
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'statusColor' => $this->status?->color() ?? '',
            'submissionDate' => $this->submission_date?->format('Y-m-d') ?? '',
            'expiryDate' => $this->expiry_date?->format('Y-m-d') ?? '',
            'sentAt' => $this->sent_at?->toISOString() ?? '',
            'viewedAt' => $this->viewed_at?->toISOString() ?? '',
            'rejectionReason' => $this->rejection_reason ?? '',
            'rejectionNotes' => $this->rejection_notes ?? '',
            'isFastTrack' => (bool) $this->is_fast_track,
            'fastTrackReason' => $this->fast_track_reason ?? '',
            'internalNotes' => $this->internal_notes ?? '',
            'isExpired' => $this->isExpired(),
            'canBeEdited' => $this->canBeEdited(),

            // Relations
            'lead' => $this->whenLoaded('lead', function () {
                return [
                    'leadId' => $this->lead->id,
                    'name' => $this->lead->name ?? '',
                    'email' => $this->lead->email ?? '',
                    'company' => $this->lead->company ?? '',
                    'projectName' => $this->lead->project_name ?? '',
                ];
            }),
            'template' => $this->whenLoaded('template', function () {
                return [
                    'templateId' => $this->template->id,
                    'name' => $this->template->name ?? '',
                    'type' => $this->template->type ?? '',
                ];
            }),
            'quotationsCount' => $this->whenLoaded('quotations', fn () => $this->quotations->count()),

            // Audit
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
        ];
    }
}
