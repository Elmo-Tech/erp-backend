<?php

namespace App\Modules\Report\Actions;

use App\Services\Reporting\ProfitabilityService;
use App\Services\Reporting\QualityService;
use App\Services\Reporting\UserPerformanceService;

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
