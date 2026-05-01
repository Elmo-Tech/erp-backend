<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use Illuminate\Support\Facades\DB;

class CreateProjectFromQuotationAction
{
    public function __construct(
        private ConvertQuotationToProjectAction $convertQuotationToProjectAction
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Project
    {
        return DB::transaction(fn () => $this->convertQuotationToProjectAction->execute(
            $data['quotationId'],
            [
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'deadline' => $data['deadline'] ?? null,
            ]
        ));
    }
}
