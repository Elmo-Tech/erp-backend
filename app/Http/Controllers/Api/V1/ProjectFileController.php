<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Project\ProjectFileResource;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectFileController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        if ($user?->hasRole('client') && $project->client_id !== $user->client?->id) {
            return ApiResponse::error('Project not found', 404);
        }

        $query = $project->files()->orderBy('created_at', 'desc');

        if ($user?->hasRole('client')) {
            $query->where('is_client_visible', true);
        }

        $files = $query->get();

        return ApiResponse::success([
            'files' => ProjectFileResource::collection($files),
        ], 'Project files retrieved successfully');
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        if (! $this->canUploadFiles($request, $project)) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:file,link',
            'file' => 'required_if:type,file|file|max:51200', // 50MB max
            'url' => 'required_if:type,link|nullable|url',
            'description' => 'nullable|string',
            'category' => 'nullable|in:general,design,document,deliverable',
            'isClientVisible' => 'nullable|boolean',
            'isDownloadable' => 'nullable|boolean',
            'visibleToRoles' => 'nullable|array',
        ]);

        $data = [
            'project_id' => $project->id,
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
            'category' => $request->category ?? 'general',
            'is_client_visible' => $request->isClientVisible ?? false,
            'is_downloadable' => $request->isDownloadable ?? true,
            'visible_to_roles' => $request->visibleToRoles,
        ];

        if ($request->type === 'file' && $request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store("projects/{$project->id}/files", 'public');

            $data['file_path'] = $path;
            $data['mime_type'] = $file->getMimeType();
            $data['size'] = $file->getSize();
        } elseif ($request->type === 'link') {
            $data['url'] = $request->url;
        }

        $projectFile = ProjectFile::create($data);

        return ApiResponse::success(
            new ProjectFileResource($projectFile),
            'File added successfully',
            201
        );
    }

    public function show(Request $request, Project $project, ProjectFile $file): JsonResponse
    {
        if ($file->project_id !== $project->id || ! $file->canUserView($request->user())) {
            return ApiResponse::error('File not found', 404);
        }

        return ApiResponse::success(
            new ProjectFileResource($file),
            'File retrieved successfully'
        );
    }

    public function update(Request $request, Project $project, ProjectFile $file): JsonResponse
    {
        if ($file->project_id !== $project->id) {
            return ApiResponse::error('File not found', 404);
        }

        if (! $this->canManageFiles($request, $project)) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|in:general,design,document,deliverable',
            'isClientVisible' => 'nullable|boolean',
            'isDownloadable' => 'nullable|boolean',
            'visibleToRoles' => 'nullable|array',
        ]);

        $file->update([
            'name' => $request->name ?? $file->name,
            'description' => $request->description ?? $file->description,
            'category' => $request->category ?? $file->category,
            'is_client_visible' => $request->isClientVisible ?? $file->is_client_visible,
            'is_downloadable' => $request->isDownloadable ?? $file->is_downloadable,
            'visible_to_roles' => $request->visibleToRoles ?? $file->visible_to_roles,
        ]);

        return ApiResponse::success(
            new ProjectFileResource($file),
            'File updated successfully'
        );
    }

    public function destroy(Request $request, Project $project, ProjectFile $file): JsonResponse
    {
        if ($file->project_id !== $project->id) {
            return ApiResponse::error('File not found', 404);
        }

        if (! $this->canDeleteFiles($request, $project)) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        // Delete physical file if exists
        if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();

        return ApiResponse::success(null, 'File deleted successfully');
    }

    private function canUploadFiles(Request $request, Project $project): bool
    {
        $user = $request->user();

        if (! $user || $user->hasRole('client')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->can('manage_project_files')
            || $user->can('upload_project_files')
            || $project->members()->where('user_id', $user->id)->exists();
    }

    private function canManageFiles(Request $request, Project $project): bool
    {
        $user = $request->user();

        if (! $user || $user->hasRole('client')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->can('manage_project_files');
    }

    private function canDeleteFiles(Request $request, Project $project): bool
    {
        $user = $request->user();

        if (! $user || $user->hasRole('client')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->can('delete_project_files')
            || $user->can('manage_project_files');
    }
}
