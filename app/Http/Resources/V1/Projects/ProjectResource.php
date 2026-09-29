<?php

namespace App\Http\Resources\V1\Projects;

use App\Http\Resources\V1\Project\ProjectFileResource;
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
        $simplifiedMode = (bool) config('simplification.enabled');
        $canViewBudget = ! $simplifiedMode && $user && $user->can('view_project_budget');
        $isClient = $user?->hasRole('client') ?? false;

        if ($isClient) {
            return [
                'projectId' => $this->id,
                'title' => $this->title ?? '',
                'status' => $this->status?->value ?? '',
                'statusLabel' => $this->status?->label() ?? '',
                'deadline' => $this->deadline?->toDateString() ?? '',
                'progress' => (float) ($this->progress ?? 0),
                'files' => $this->whenLoaded('files', function () use ($user) {
                    return ProjectFileResource::collection(
                        $this->files->filter(fn ($file) => $file->canUserView($user))->values()
                    );
                }),
                'createdAt' => $this->created_at?->toISOString() ?? '',
                'updatedAt' => $this->updated_at?->toISOString() ?? '',
            ];
        }

        $data = [
            'projectId' => $this->id,
            'clientId' => $this->client_id ?? 0,
            'projectTypeId' => $this->project_type_id,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'status' => $this->status?->value ?? '',
            'previousStatus' => $this->previous_status?->value ?? '',
            'deadline' => $this->deadline?->toDateString() ?? '',
            'progress' => (float) ($this->progress ?? 0),
            'totalTimeSpent' => (int) ($this->total_time_spent ?? 0),
            'isPinned' => $this->isPinnedBy($user?->id),
            'client' => $this->whenLoaded('client', function () {
                return [
                    'clientId' => $this->client->id,
                    'name' => $this->client->name ?? '',
                    'email' => $this->client->email ?? '',
                    'company' => $this->client->company ?? '',
                    'phone' => $this->client->phone ?? '',
                ];
            }),
            'projectType' => $this->whenLoaded('projectType', fn () => $this->projectType ? [
                'projectTypeId' => $this->projectType->id,
                'value' => $this->projectType->value,
                'label' => $this->projectType->label,
                'color' => $this->projectType->color,
                'icon' => $this->projectType->icon,
                'isActive' => $this->projectType->is_active,
            ] : null),
            'members' => $this->when(! $isClient && $this->relationLoaded('members'), function () {
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
            'files' => $this->whenLoaded('files', fn () => ProjectFileResource::collection($this->files)),
            'googleDriveFolderId' => $this->google_drive_folder_id,
            'googleDriveFolderName' => $this->google_drive_folder_name,
            'googleDriveFolderUrl' => $this->google_drive_folder_url,
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
        ];

        if (! $simplifiedMode) {
            $data['quotationId'] = $this->quotation_id ?? 0;
            $data['budget'] = $canViewBudget ? (float) ($this->budget ?? 0.0) : null;
            $data['canViewBudget'] = $canViewBudget;
            $data['uiApprovedAt'] = $this->ui_approved_at?->toISOString() ?? '';
            $data['autoApprovalJobId'] = $this->auto_approval_job_id ?? '';
            $data['quotation'] = $this->when($canViewBudget, function () {
                return $this->whenLoaded('quotation', function () {
                    return new QuotationResource($this->quotation);
                });
            });
        }

        return $data;
    }
}
