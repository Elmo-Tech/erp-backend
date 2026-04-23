<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectFile;
use Illuminate\Http\Request;

class StoreProjectFileAction
{
    public function execute(Project $project, Request $request): ProjectFile
    {
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

        return ProjectFile::create($data);
    }
}
