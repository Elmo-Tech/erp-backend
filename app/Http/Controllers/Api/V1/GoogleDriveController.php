<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\GoogleDriveConnection;
use App\Models\Project;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GoogleDriveController extends Controller
{
    public function __construct(private readonly GoogleDriveService $drive)
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function oauthUrl(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super-admin']) && ! $request->user()?->can('manage_project_files')) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        return ApiResponse::success([
            'url' => $this->drive->oauthUrl($request->string('state')->toString() ?: null),
            'connected' => GoogleDriveConnection::query()->exists(),
        ], 'Google Drive OAuth URL generated');
    }

    public function oauthCallback(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super-admin']) && ! $request->user()?->can('manage_project_files')) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $data = $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $this->drive->storeAuthorizationCode($data['code']);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }

        return ApiResponse::success(null, 'Google Drive connected successfully');
    }

    public function folders(Request $request, int|string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $this->canManageDriveFolder($request, $project)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        try {
            return ApiResponse::success([
                'folders' => $this->drive->folders($request->string('search')->toString() ?: null),
            ], 'Google Drive folders retrieved successfully');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }
    }

    public function connectFolder(Request $request, int|string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $this->canManageDriveFolder($request, $project)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $data = $request->validate([
            'folderId' => 'required|string|max:255',
            'folderName' => 'required|string|max:255',
            'folderUrl' => 'nullable|url|max:2048',
        ]);

        $project->update([
            'google_drive_folder_id' => $data['folderId'],
            'google_drive_folder_name' => $data['folderName'],
            'google_drive_folder_url' => $data['folderUrl'] ?? null,
        ]);

        return ApiResponse::success([
            'googleDriveFolderId' => $project->google_drive_folder_id,
            'googleDriveFolderName' => $project->google_drive_folder_name,
            'googleDriveFolderUrl' => $project->google_drive_folder_url,
        ], 'Google Drive folder connected successfully');
    }

    public function files(Request $request, int|string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $this->canUseDriveFiles($request, $project)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        if (! $project->google_drive_folder_id) {
            return ApiResponse::error('Google Drive folder is not connected', 422);
        }

        try {
            return ApiResponse::success([
                'files' => $this->drive->files($project->google_drive_folder_id),
            ], 'Google Drive files retrieved successfully');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }
    }

    public function upload(Request $request, int|string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $this->canUploadDriveFiles($request, $project)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        if (! $project->google_drive_folder_id) {
            return ApiResponse::error('Google Drive folder is not connected', 422);
        }

        $request->validate([
            'file' => 'required|file|max:51200',
        ]);

        try {
            return ApiResponse::success([
                'file' => $this->drive->upload($project->google_drive_folder_id, $request->file('file')),
            ], 'File uploaded to Google Drive successfully', 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }
    }

    private function canManageDriveFolder(Request $request, Project $project): bool
    {
        $user = $request->user();

        if (! $user || $user->hasRole('client')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->can('manage_project_files')
            || ($user->can('upload_project_files') && $project->members()->where('user_id', $user->id)->exists());
    }

    private function findProject(int|string $project): Project
    {
        return Project::query()->withoutGlobalScopes()->findOrFail($project);
    }

    private function canUseDriveFiles(Request $request, Project $project): bool
    {
        $user = $request->user();

        if (! $user || $user->hasRole('client')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->can('view_project_files')
            || $user->can('manage_project_files')
            || $project->members()->where('user_id', $user->id)->exists();
    }

    private function canUploadDriveFiles(Request $request, Project $project): bool
    {
        $user = $request->user();

        if (! $user || $user->hasRole('client')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->can('upload_project_files')
            || $user->can('manage_project_files')
            || $project->members()->where('user_id', $user->id)->exists();
    }
}
