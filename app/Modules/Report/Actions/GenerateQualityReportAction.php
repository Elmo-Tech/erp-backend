<?php

namespace App\Modules\Report\Actions;

use App\Services\Reporting\QualityService;

class GenerateQualityReportAction
{
    public function __construct(
        private QualityService $qualityService
    ) {}

    public function execute(array $filters = [], int $limit = 10): array
    {
        return [
            'overview' => $this->qualityService->getQualityOverview(),
            'bounceRateByDeveloper' => $this->qualityService->getBounceRateByDeveloper($filters),
            'topBouncingTasks' => $this->qualityService->getTopBouncingTasks($limit, $filters),
        ];
    }
}
