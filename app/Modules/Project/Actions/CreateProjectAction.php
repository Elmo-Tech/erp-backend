<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;

class CreateProjectAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Project
    {
        return Project::create([
            'client_id' => $data['clientId'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'budget' => $data['budget'],
            'deadline' => $data['deadline'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'quotation_id' => null,
        ]);
    }
}
