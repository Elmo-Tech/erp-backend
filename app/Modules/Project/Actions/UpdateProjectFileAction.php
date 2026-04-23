<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectFile;
use Illuminate\Http\Request;
use RuntimeException;

class UpdateProjectFileAction
{
    public function execute(Project $project, ProjectFile $file, Request $request): ProjectFile
    {
        if ($file->project_id !== $project->id) {
            throw new RuntimeException('File not found');
        }

        $file->update([
            'name' => $request->name ?? $file->name,
            'description' => $request->description ?? $file->description,
            'category' => $request->category ?? $file->category,
            'is_client_visible' => $request->isClientVisible ?? $file->is_client_visible,
            'is_downloadable' => $request->isDownloadable ?? $file->is_downloadable,
            'visible_to_roles' => $request->visibleToRoles ?? $file->visible_to_roles,
        ]);

        return $file->fresh();
    }
}
