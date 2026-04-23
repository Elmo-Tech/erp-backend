<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteProjectFileAction
{
    public function execute(Project $project, ProjectFile $file): void
    {
        if ($file->project_id !== $project->id) {
            throw new RuntimeException('File not found');
        }

        if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();
    }
}
