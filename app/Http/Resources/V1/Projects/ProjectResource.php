<?php

namespace App\Http\Resources\V1\Projects;

use App\Http\Resources\V1\Quotations\QuotationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * 
     * Budget fields are hidden from users without 'view_project_budget' permission.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewBudget = $user && $user->can('view_project_budget');

        return [
            'projectId' => $this->id,
            'clientId' => $this->client_id ?? 0,
            'quotationId' => $this->quotation_id ?? 0,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'status' => $this->status?->value ?? '',
            'previousStatus' => $this->previous_status?->value ?? '',
            // Budget is only visible to users with permission
            'budget' => $canViewBudget ? (float) ($this->budget ?? 0.0) : null,
            'canViewBudget' => $canViewBudget,
            'deadline' => $this->deadline?->toDateString() ?? '',
            'uiApprovedAt' => $this->ui_approved_at?->toISOString() ?? '',
            'autoApprovalJobId' => $this->auto_approval_job_id ?? '',
            'progress' => (float) ($this->progress ?? 0),
            'totalTimeSpent' => (int) ($this->total_time_spent ?? 0),
            'client' => $this->whenLoaded('client', function () {
                return [
                    'clientId' => $this->client->id,
                    'name' => $this->client->name ?? '',
                    'email' => $this->client->email ?? '',
                    'company' => $this->client->company ?? '',
                    'phone' => $this->client->phone ?? '',
                ];
            }),
            // Quotation contains pricing info, hide from non-budget viewers
            'quotation' => $this->when($canViewBudget, function () {
                return $this->whenLoaded('quotation', function () {
                    return new QuotationResource($this->quotation);
                });
            }),
            'members' => $this->whenLoaded('members', function () {
                return $this->members->map(function ($member) {
                    return [
                        'memberId' => $member->id,
                        'projectId' => $member->project_id,
                        'userId' => $member->user_id,
                        'role' => $member->role ?? '',
                        'user' => $member->user ? [
                            'userId' => $member->user->id,
                            'name' => $member->user->name ?? '',
                            'email' => $member->user->email ?? '',
                            'avatar' => $member->user->avatar,
                        ] : null,
                        'createdAt' => $member->created_at?->toISOString() ?? '',
                    ];
                });
            }),
            'files' => $this->whenLoaded('files', function () {
                return $this->files->map(function ($file) {
                    return [
                        'fileId' => $file->id,
                        'projectId' => $file->project_id,
                        'name' => $file->name ?? '',
                        'type' => $file->type ?? 'file',
                        'fileUrl' => $file->file_url ?? '',
                        'mimeType' => $file->mime_type ?? '',
                        'size' => (int) ($file->size ?? 0),
                        'description' => $file->description ?? '',
                        'category' => $file->category ?? '',
                        'isClientVisible' => (bool) $file->is_client_visible,
                        'isDownloadable' => (bool) $file->is_downloadable,
                        'visibleToRoles' => $file->visible_to_roles ?? [],
                        'createdAt' => $file->created_at?->toISOString() ?? '',
                    ];
                });
            }),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
        ];
    }
}
