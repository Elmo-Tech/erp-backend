<?php

namespace App\Modules\Report\Actions;

use App\Modules\Report\Services\ProfitabilityService;
use App\Modules\Report\Services\QualityService;
use App\Modules\Report\Services\UserPerformanceService;

class GenerateAdminOverviewReportAction
{
    public function __construct(
        private ProfitabilityService $profitabilityService,
        private QualityService $qualityService,
        private UserPerformanceService $userPerformanceService
    ) {}

    public function execute(): array
    {
        return [
            'profitability' => $this->profitabilityService->getOverviewStats(),
            'quality' => $this->qualityService->getQualityOverview(),
            'attendance' => $this->userPerformanceService->getAttendanceAnalytics(),
        ];
    }
}
